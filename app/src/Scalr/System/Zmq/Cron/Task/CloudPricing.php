<?php
namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject, DateTime, stdClass, DateTimeZone, Exception;
use http\Client\Request;
use Scalr\System\Zmq\Cron\AbstractTask;
use Scalr\Stats\CostAnalytics\Entity\PriceEntity;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Service\Aws;
use Scalr\System\Zmq\Cron\AbstractPayload;

/**
 * CloudPricing
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0 (10.09.2014)
 */
class CloudPricing extends AbstractTask
{

    /**
     * @var Request
     */
    private $request;

    public static $mapping = [
        'us-east'        => 'us-east-1',
        'us-west'        => 'us-west-1',
        'us-east-1'      => 'us-east-1',
        'us-west-1'      => 'us-west-1',
        'us-west-2'      => 'us-west-2',
        'eu-ireland'     => 'eu-west-1',
        'eu-west-1'      => 'eu-west-1',
        'eu-central-1'   => 'eu-central-1',
        'sa-east-1'      => 'sa-east-1',
        'apac-sin'       => 'ap-southeast-1',
        'ap-southeast-1' => 'ap-southeast-1',
        'apac-tokyo'     => 'ap-northeast-1',
        'ap-northeast-1' => 'ap-northeast-1',
        'ap-northeast-2' => 'ap-northeast-2',
        'apac-syd'       => 'ap-southeast-2',
        'ap-southeast-2' => 'ap-southeast-2',
        'us-gov-west-1'  => 'us-gov-west-1',
    ];

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::enqueue()
     */
    public function enqueue()
    {
        $queue = new ArrayObject([]);

        if (!\Scalr::getContainer()->analytics->enabled) {
        	$this->log("INFO", "Terminating the process as Cost analytics is disabled in the config.");
        	exit;
        }

        if (SettingEntity::getValue(SettingEntity::ID_FORBID_AUTOMATIC_UPDATE_AWS_PRICES)) {
            $this->log("INFO", "Terminating the process because of overriding AWS prices has been forbidden by financial admin.");
            exit;
        }

        $urls = array(
            'https://a0.awsstatic.com/pricing/1/ec2/linux-od.min.js',
        	'https://a0.awsstatic.com/pricing/1/ec2/mswin-od.min.js',
            'https://a0.awsstatic.com/pricing/1/ec2/previous-generation/linux-od.min.js',
            'https://a0.awsstatic.com/pricing/1/ec2/previous-generation/mswin-od.min.js'
        );

        foreach ($urls as $link) {
            $json = trim(preg_replace('/^.+?callback\((.+?)\);\s*$/sU', '\\1', $this->getPricingContent($link)));

            $data = json_decode(preg_replace('/(\w+):/', '"\\1":', $json));

            if (!empty($data->config->regions)) {
                foreach ($data->config->regions as $rd) {
                    $rd->url = basename($link);
                    $queue->append($rd);
                }
            }
        }

        return $queue;
    }

    /**
     * Gets pricing content
     *
     * @param string $link
     *
     * @return string
     */
    private function getPricingContent($link)
    {
        $request = $this->getRequest();
        $request->setRequestUrl($link);

        return \Scalr::getContainer()->http->sendRequest($this->request)->getBody()->toString();
    }

    /**
     * Gets configured http Request
     *
     * @return Request
     */
    private function getRequest()
    {
        if(!$this->request) {
            $opt = ['timeout' => 15];

            if (\Scalr::config('scalr.aws.use_proxy')) {
                $proxy = \Scalr::config('scalr.connections.proxy');
                if (in_array($proxy['use_on'], array('both', 'scalr'))) {
                    $opt['proxyhost'] = $proxy['host'];
                    $opt['proxyport'] = $proxy['port'];
                    $opt['proxytype'] = $proxy['type'];

                    if (!empty($proxy['pass']) && !empty($proxy['user'])) {
                        $opt['proxyauth'] = "{$proxy['user']}:{$proxy['pass']}";
                        $opt['proxyauthtype'] = $proxy['authtype'];
                    }
                }
            }

            $this->request = new Request("GET");
            $this->request->setOptions($opt);
        } else {
            $this->request->setOptions([ 'cookiesession' => true ]);
        }

        return $this->request;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::worker()
     */
    public function worker($request)
    {
        if (!\Scalr::getContainer()->analytics->enabled) {
            $this->log("WARN", "Cannot process the request. Cloud cost analytics is disabled in config.");
            return false;
        }

        $this->log("INFO", "Processing region: %s", $request->region);

        $now = new DateTime('now', new DateTimeZone('UTC'));

        $cadb = \Scalr::getContainer()->cadb;

        $os = (strpos($request->url, 'linux') !== false ? PriceEntity::OS_LINUX : PriceEntity::OS_WINDOWS);

        foreach ($request->instanceTypes as $it) {
            if (!isset(self::$mapping[$request->region])) {
                throw new Exception(sprintf("Region %s does not exist in the mapping.", $request->region));
            }

            $region = self::$mapping[$request->region];

            $latest = [];

            //Gets latest prices for all instance types from current region.
            $res = $cadb->Execute("
                SELECT p.instance_type, ph.applied, p.os, p.name, HEX(p.price_id) `price_id`, p.cost
                FROM price_history ph
                JOIN prices p ON p.price_id = ph.price_id
                LEFT JOIN price_history ph2 ON ph2.platform = ph.platform
                    AND ph2.cloud_location = ph.cloud_location
                    AND ph2.account_id = ph.account_id
                    AND ph2.url = ph.url
                    AND ph2.applied > ph.applied AND ph2.applied <= ?
                LEFT JOIN prices p2 ON p2.price_id = ph2.price_id
                    AND p2.instance_type = p.instance_type
                    AND p2.os = p.os
                WHERE ph.account_id = 0 AND p2.price_id IS NULL
                AND ph.platform = 'ec2'
                AND ph.cloud_location = ?
                AND ph.url = ''
                AND ph.applied <= ?
            ", [
                $now->format('Y-m-d'),
                $region,
                $now->format('Y-m-d')
            ]);

            while ($rec = $res->FetchRow()) {
                $latest[$rec['instance_type']][$rec['os']] = [
                	'applied'  => $rec['applied'],
                    'price_id' => $rec['price_id'],
                    'cost'     => $rec['cost'],
                ];
            }

            $needUpdate = false;
            foreach ($it->sizes as $sz) {
                foreach ($sz->valueColumns as $v) {
                    if (!is_numeric($v->prices->USD) || $v->prices->USD < 0.000001) {
                        continue;
                    }

                    if (!isset($latest[$sz->size][$os])) {
                        $needUpdate = true;
                    } else if (abs(($latest[$sz->size][$os]['cost'] - $v->prices->USD) / $v->prices->USD) > 0.000001) {
                        $needUpdate = true;
                        $latest[$sz->size][$os]['cost'] = $v->prices->USD;
                    } else {
                        continue;
                    }

                    $latest[$sz->size][$os] = ['cost' => $v->prices->USD];
                }
            }

            if ($needUpdate) {
                $priceid = $cadb->GetOne("
                    SELECT HEX(`price_id`) AS `price_id`
                    FROM price_history
                    WHERE platform = 'ec2'
                    AND url = ''
                    AND cloud_location = ?
                    AND applied = ?
                    AND account_id = 0
                    LIMIT 1
                ", [
                	$region,
                    $now->format('Y-m-d')
                ]);

                if (!$priceid) {
                    $priceid = str_replace('-', '', \Scalr::GenerateUID());

                    $cadb->Execute("
                        INSERT price_history
                        SET price_id = UNHEX(?),
                            platform = 'ec2',
                            url = '',
                            cloud_location = ?,
                            account_id = 0,
                            applied = ?,
                            deny_override = 0
                    ", [
                        $priceid,
                        $region,
                        $now->format('Y-m-d')
                    ]);
                }

                foreach ($latest as $instanceType => $ld) {
                    foreach ($ld as $os => $v) {
                        $cadb->Execute("
                            REPLACE prices
                            SET price_id = UNHEX(?),
                                instance_type = ?,
                                name = ?,
                                os = ?,
                                cost = ?
                        ", [
                            $priceid,
                            $instanceType,
                            $instanceType,
                            $os,
                            $v['cost']
                        ]);
                    }
                }
            }
        }

        $ret = new stdClass();
        $ret->url = $request->url;
        $ret->region = $request->region;

        return $ret;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\AbstractTask::onResponse()
     */
    public function onResponse(AbstractPayload $payload)
    {
        $response = $payload->body;

        if ($payload->code == 200) {
            $this->log("INFO", "Url:%s, region:%s has been processed.", $response->url, $response->region);
        }
    }
}