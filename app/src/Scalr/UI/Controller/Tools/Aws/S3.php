<?php

use Scalr\Service\Aws\S3\DataType\BucketData;
use Scalr\Service\Aws\CloudFront\DataType\DistributionData;
use Scalr\Service\Aws\CloudFront\DataType\DistributionConfigData;
use Scalr\Service\Aws\CloudFront\DataType\DistributionConfigOriginData;
use Scalr\Service\Aws\CloudFront\DataType\CacheBehaviorData;
use Scalr\Service\Aws\CloudFront\DataType\DistributionS3OriginConfigData;
use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\Service\Aws;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\DataType\ErrorData;

class Scalr_UI_Controller_Tools_Aws_S3 extends Scalr_UI_Controller
{
    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        if (!parent::hasAccess() || !$this->request->isAllowed(Acl::RESOURCE_AWS_S3)) {
            return false;
        }

        $enabledPlatforms = $this->getEnvironment()->getEnabledPlatforms();

        if (!in_array(SERVER_PLATFORMS::EC2, $enabledPlatforms)) {
            throw new Exception("You need to enable EC2 platform for current environment");
        }

        return true;
    }

    public function defaultAction()
    {
        $this->manageBucketsAction();
    }

    public function manageBucketsAction()
    {
        $this->response->page('ui/tools/aws/s3/buckets.js', array(
            'locations'	=> self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false)
        ));
    }

    /**
     * Gets AWS instance
     *
     * If cloud location is not specified it will use default cloud location for
     * current User's session
     *
     * @param  string  $cloudLocation optional A Cloud Location
     * @return Aws     Returns AWS initialized for the specified cloud location
     */
    protected function getAws($cloudLocation = null)
    {
        if (empty($cloudLocation)) {
            $p = PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2);

            $cloudLocations = $p->getLocations($this->environment);

            list($cloudLocation) = each($cloudLocations);
        }

        return $this->environment->aws($cloudLocation);
    }

    public function xListBucketsAction()
    {
        $aws = $this->getAws();

        $distributions = [];

        try {
            //Retrieves the list of all distributions
            $distList = $this->getAws(Aws::REGION_US_EAST_1)->cloudFront->distribution->describe();

            /* @var $dist DistributionData */
            foreach ($distList as $dist) {
                /* @var $org DistributionConfigOriginData */
                foreach ($dist->distributionConfig->origins as $org) {
                    $distributions[preg_replace('#\.s3\.amazonaws\.com$#', '', $org->domainName)] = $dist;
                }

                unset($dist);
            }
        } catch (ClientException $e) {
            Scalr::logException($e);
        }

        // Get list of all user buckets
        $buckets = [];

        /* @var $bucket BucketData */
        foreach ($aws->s3->bucket->getList() as $bucket) {
            $bucketName = $bucket->bucketName;

            if (empty($distributions[$bucketName])) {
                $info = ["name" => $bucketName];
            } else {
                $dist = $distributions[$bucketName];

                $info = [
                    "name"    => $bucketName,
                    "cfid"    => $dist->distributionId,
                    "cfurl"   => $dist->domainName,
                    "cname"   => $dist->distributionConfig->aliases->get(0)->cname,
                    "status"  => $dist->status,
                    "enabled" => $dist->distributionConfig->enabled ? 'true' : 'false'
                ];
            }

            $c = explode("-", $info['name']);

            if ($c[0] == 'farm') {
                $hash = $c[1];

                $farm = $this->db->GetRow("
                    SELECT id, name
                    FROM farms
                    WHERE hash = ? AND env_id = ?
                    LIMIT 1
                ", [$hash, $this->environment->id]);

                if ($farm) {
                    $info['farmId'] = $farm['id'];
                    $info['farmName'] = $farm['name'];
                }
            }

            $buckets[] = $info;
        }

        $response = $this->buildResponseFromData($buckets, array('name', 'farmName'));

        $this->response->data($response);
    }

    /**
     * @param string $location    AWS Cloud Location
     * @param string $bucketName  The name of the bucket to create
     */
    public function xCreateBucketAction($location, $bucketName)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_S3, Acl::PERM_AWS_S3_MANAGE);

        // It is important to sign request with the same location as the bucket
        $aws = $this->environment->aws($location);

        try {
            $aws->s3->bucket->create($bucketName, $location);
        } catch (ClientException $e) {
            if ($e->getErrorData() && $e->getErrorData()->getCode() == ErrorData::ERR_AUTHORIZATION_HEADER_MALFORMED &&
                preg_match('/region\s+\'(.+?)\'\s+is\s+wrong.\s+expecting\s+\'(.+?)\'/', $e->getMessage(), $m) &&
                in_array($m[2], $aws->getCloudLocations())) {
                //The bucket already exists in another cloud location or even account.
                //If it is another account correct error will be displayed.
                $aws->s3->bucket->create($bucketName, $m[2]);
            } else {
                throw $e;
            }
        }

        $this->response->success('Bucket successfully created');
    }

    public function xDeleteBucketAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_S3, Acl::PERM_AWS_S3_MANAGE);

        $this->request->defineParams([
            'buckets' => ['type' => 'json']
        ]);

        $aws = $this->getAws();

        foreach ($this->getParam('buckets') as $bucketName) {
            try {
                // Delete operation should be processed in the bucket's cloud location
                $aws->s3->bucket->delete($bucketName);
            } catch (ClientException $e) {
                if ($e->getErrorData() && $e->getErrorData()->getCode() == ErrorData::ERR_AUTHORIZATION_HEADER_MALFORMED &&
                    preg_match('/region\s+\'(.+?)\'\s+is\s+wrong.\s+expecting\s+\'(.+?)\'/', $e->getMessage(), $m) &&
                    in_array($m[2], $aws->getCloudLocations())) {
                    $this->getAws($m[2])->s3->bucket->delete($bucketName);
                } else {
                    throw $e;
                }
            }
        }

        $this->response->success('Bucket(s) successfully deleted');
    }

    public function manageDistributionAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_S3, Acl::PERM_AWS_S3_MANAGE);

        $this->response->page('ui/tools/aws/s3/distribution.js');
    }

    public function xCreateDistributionAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_S3, Acl::PERM_AWS_S3_MANAGE);

        $distributionConfig = new DistributionConfigData();

        if ($this->getParam('localDomain') && $this->getParam('zone')) {
            $distributionConfig->aliases->append([
                'cname' => $this->getParam('localDomain') . '.' . $this->getParam('zone'),
            ]);
        } else if ($this->getParam('remoteDomain')) {
            $distributionConfig->aliases->append([
                'cname' => $this->getParam('remoteDomain'),
            ]);
        }

        $distributionConfig->comment = $this->getParam('comment');
        $distributionConfig->enabled = true;

        $origin = new DistributionConfigOriginData('MyOrigin', $this->getParam('bucketName') . ".s3.amazonaws.com");
        $origin->setS3OriginConfig(new DistributionS3OriginConfigData());
        $distributionConfig->origins->append($origin);
        $distributionConfig->priceClass = DistributionConfigData::PRICE_CLASS_ALL;

        $distributionConfig->setDefaultCacheBehavior(
            new CacheBehaviorData($origin->originId, CacheBehaviorData::VIEWER_PROTOCOL_POLICY_ALLOW_ALL, 3600, 86400, 31536000)
        );

        $result = $this->getAws(Aws::REGION_US_EAST_1)->cloudFront->distribution->create($distributionConfig);

        $this->db->Execute("
            INSERT INTO distributions
            SET cfid = ?,
                cfurl = ?,
                cname = ?,
                zone = ?,
                bucket = ?,
                clientid = ?
        ", [
            $result->distributionId,
            $result->domainName,
            $this->getParam('localDomain') ? $this->getParam('localDomain') : $result->distributionConfig->aliases[0]->cname,
            $this->getParam('zone')? $this->getParam('zone') : $result->distributionConfig->aliases[0]->cname,
            $this->getParam('bucketName'),
            $this->user->getAccountId()
        ]);

        $this->response->success("Distribution successfully created");
    }

    public function xUpdateDistributionAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_S3, Acl::PERM_AWS_S3_MANAGE);

        $dist = $this->getAws(Aws::REGION_US_EAST_1)->cloudFront->distribution->fetch($this->getParam('id'));
        $dist->distributionConfig->enabled = ($this->getParam('enabled') == 'true');
        $dist->setConfig($dist->distributionConfig, $dist->getETag());

        $this->response->success("Distribution successfully updated");
    }

    public function xDeleteDistributionAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_S3, Acl::PERM_AWS_S3_MANAGE);

        $dist = $this->getAws(Aws::REGION_US_EAST_1)->cloudFront->distribution->fetch($this->getParam('id'));
        $result = $dist->delete();

        $info = $this->db->GetRow("SELECT * FROM distributions WHERE cfid=? LIMIT 1", array($this->getParam('id')));

        if ($info) {
            $this->db->Execute("DELETE FROM distributions WHERE cfid=?", array($this->getParam('id')));
        }

        $this->response->success("Distribution successfully removed");
    }

    public function xListZonesAction()
    {
        $zones = $this->db->GetAll("SELECT zone_name FROM dns_zones WHERE status!=? AND env_id=?", [
            DNS_ZONE_STATUS::PENDING_DELETE,
            $this->getEnvironmentId()
        ]);

        $this->response->data(['data' => $zones]);
    }
}
