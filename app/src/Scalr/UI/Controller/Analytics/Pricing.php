<?php

use Scalr\Stats\CostAnalytics\Entity\PriceHistoryEntity;
use Scalr\Stats\CostAnalytics\Entity\PriceEntity;
use Scalr\Modules\Platforms\Openstack\OpenstackPlatformModule;
use Scalr\Modules\Platforms\Cloudstack\CloudstackPlatformModule;
use Scalr\Modules\Platforms\Eucalyptus\EucalyptusPlatformModule;
use Scalr\Modules\PlatformFactory;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Modules\PlatformModuleInterface;
use Scalr\Exception\Http\NotFoundException;
use Scalr\UI\Request\JsonData;
use Scalr\Model\Collections\ArrayCollection;

class Scalr_UI_Controller_Analytics_Pricing extends Scalr_UI_Controller
{

    public function hasAccess()
    {
        return $this->user->isAdmin();
    }

    public function defaultAction()
    {
        //Platforms should be in the same order everywhere
        $platforms = array_values(array_intersect(array_keys(SERVER_PLATFORMS::GetList()), array_merge([
                SERVER_PLATFORMS::EC2,
            ],
            PlatformFactory::getOpenstackBasedPlatforms(),
            PlatformFactory::getCloudstackBasedPlatforms()
        )));

        $this->response->page('ui/analytics/pricing/view.js',
            [
                'platforms'             => $platforms,
                'forbidAutomaticUpdate' => [
                    SERVER_PLATFORMS::EC2 => !!SettingEntity::getValue(SettingEntity::ID_FORBID_AUTOMATIC_UPDATE_AWS_PRICES),
                 ],
            ],
            [],
            ['ui/analytics/pricing/view.css']
        );
    }

    /**
     * Loads price details
     *
     * @param    PriceHistoryEntity $price  Existing price history entity
     * @throws   \Exception
     * @return   array  Returns list of the prices
     */
    public function getPrice(PriceHistoryEntity $price)
    {
        $result = array();
        foreach ($price->getDetails() as $priceEntity) {
            /* @var $priceEntity PriceEntity */
            if (!isset($result[$priceEntity->instanceType]))
                $result[$priceEntity->instanceType] = [
                    'type' => $priceEntity->instanceType,
                    'name' => $priceEntity->name,
                ];

            if ($priceEntity->os == PriceEntity::OS_LINUX) {
                $system = 'priceLinux';
            } else if ($priceEntity->os == PriceEntity::OS_WINDOWS) {
                $system = 'priceWindows';
            } else {
                throw new \Exception(sprintf("Unexpected os:%d. Not implemented yet.", $priceEntity->os));
            }

            $result[$priceEntity->instanceType][$system] = $priceEntity->cost;
        }

        return array_values($result);
    }


    /**
     * Gets pricing
     *
     * @param   string    $platform       The cloud platform
     * @param   string    $cloudLocation  The cloud location
     * @param   string    $url            The cloud endpoint url
     * @param   DateTime  $effectiveDate  The date on wich prices should be applied
     * @return  PriceHistoryEntity
     */
    public function getPlatformPricing($platform, $cloudLocation, $url, $effectiveDate)
    {
        $service = $this->getContainer()->analytics->prices;

        $result = array();
        $price = new PriceHistoryEntity();
        $price->platform = $platform;
        $price->cloudLocation = $cloudLocation;
        $price->accountId = $this->request->getUser()->getAccountId();
        $price->url = $service->normalizeUrl($url);
        $price->applied = ($effectiveDate instanceof DateTime) ? $effectiveDate : null;

        if ($service->get($price)) {
            $result['denyOverride'] = $price->denyOverride;
            $result['prices'] = $this->getPrice($price);
        }

        return $result;
    }

    /**
     * xGetPlatformPricingAction
     *
     * @param   string     $platform       The cloud platform
     * @param   string     $cloudLocation  The cloud location
     * @param   string     $url            optional The cloud's endpoint url
     * @param   string     $effectiveDate  optional The date on which prises should be applied
     */
    public function xGetPlatformPricingAction($platform, $cloudLocation, $url = '', $effectiveDate = null)
    {
        list(,$effectiveDate) = $this->handleEffectiveDate($effectiveDate);

        $this->response->data(['data' => $this->getPlatformPricing(
            $platform, $cloudLocation, $url, $effectiveDate
        )]);
    }

    /**
     * xGetPlatformPricingHistoryAction
     *
     * @param   string     $platform       The cloud platform
     * @param   string     $cloudLocation  The cloud location
     * @param   string     $url            optional The cloud's endpoint url
     */
    public function xGetPlatformPricingHistoryAction($platform, $cloudLocation, $url = '')
    {
        $result = [];
        $types = [];

        $history = $this->getContainer()->analytics->prices->getHistory(
            $platform, $cloudLocation, $url, $this->user->getAccountId()
        );

        foreach ($history as $item) {
            /* @var $item PriceHistoryEntity */
            $dt = Scalr_Util_DateTime::convertDateTime($item->applied, null, 'Y-m-d');
            $result[$dt] = $this->getPrice($item);
            foreach ($result[$dt] as $v)
                $types[$v['type']] = [
                    'type' => $v['type'],
                    'name' => $v['name']
                ];
        }

        $this->response->data([
            'types'   => array_values($types),
            'history' => $result
        ]);
    }

    /**
     * xGetPlatformLocationsAction
     *
     * @param    string    $platform  Platform name
     * @param    string    $url       optional The endpoint url
     * @param    string    $envId     optional The identifier of the environment
     */
    public function xGetPlatformLocationsAction($platform, $url = '', $envId = null)
    {
        $existingLocations = [];

        $locations = $this->getContainer()->analytics->prices->getCloudLocations($platform);
        foreach ($locations as $key => $value) {
            if ($key == $url) {
                foreach ($value as $cloudLocation) {
                    $result[] = [
                        'url'           => $key,
                        'cloudLocation' => $cloudLocation
                    ];
                    $existingLocations[] = $key. ';' . $cloudLocation;
                }
            }
        }

        $pm = PlatformFactory::NewPlatform($platform);

        $env = null;
        if ($envId) {
            $env = Scalr_Environment::init()->loadById($envId);
        } else if ($platform == SERVER_PLATFORMS::EC2) {
            //Search for govcloud environment
            $gcenvid = $this->db->GetOne("
                SELECT e.id
                FROM client_environments e
                JOIN client_environment_properties p ON p.env_id = e.id AND p.name = ?
                JOIN clients c ON c.id = e.client_id
                WHERE p.value = ? AND e.status = ? AND c.status = ?
                LIMIT 1
            ",[
                Ec2PlatformModule::ACCOUNT_TYPE,
                Ec2PlatformModule::ACCOUNT_TYPE_GOV_CLOUD,
                Scalr_Environment::STATUS_ACTIVE,
                Scalr_Account::STATUS_ACTIVE,
            ]);

            if ($gcenvid) {
                $gcenv = Scalr_Environment::init()->loadById($gcenvid);
                $aLocations = $pm->getLocations($gcenv);
            }
        }

        foreach (array_merge((!empty($aLocations) ? $aLocations : []), $pm->getLocations($env)) as $location => $name) {
            if (!in_array($url . ';' . $location, $existingLocations)) {
                $result[] = [
                    'url'           => $url,
                    'cloudLocation' => $location
                ];
            }
        }

        if (!empty($result[0])) {
            $result[0] = $this->getTypesWithPrices(
                $result[0]['cloudLocation'],
                $result[0]['url'],
                $pm,
                $platform,
                null,
                $env
            );
        }

        $response = ['data' => $result];

        $this->response->data($response);
    }

    /**
     * xGetPlatformEndpointsAction
     *
     * @param    string    $platform  The cloud platform
     */
    public function xGetPlatformEndpointsAction($platform)
    {

        if (PlatformFactory::isOpenstack($platform)) {
            $key = $platform . '.' . OpenstackPlatformModule::KEYSTONE_URL;
        } else if (PlatformFactory::isCloudstack($platform)) {
            $key = $platform . '.' . CloudstackPlatformModule::API_URL;
        } else if ($platform == SERVER_PLATFORMS::EUCALYPTUS) {
            $key = EucalyptusPlatformModule::EC2_URL;
        }

        if (isset($key)) {
            $pm = PlatformFactory::NewPlatform($platform);

            $rs = $this->db->Execute("
                SELECT DISTINCT cep.`env_id`, cep.`group`
                FROM client_environment_properties cep
                JOIN client_environments ce ON ce.id = cep.env_id
                JOIN clients c ON c.id = ce.client_id
                WHERE c.status = ? AND cep.name = ? AND ce.status = ?
                GROUP BY value
            ", [Scalr_Account::STATUS_ACTIVE, $key, Scalr_Environment::STATUS_ACTIVE]);

            $endpoints = [];
            $lastException = '';
            while ($rec = $rs->FetchRow()) {
                $id = $rec['env_id'];
                $group = $rec['group'];
                try {
                    $env = Scalr_Environment::init()->loadById($id);
                } catch (Exception $e) {
                    $lastException = $e->getMessage();
                    continue;
                }

                $url = $this->getContainer()->analytics->prices->normalizeUrl($env->getPlatformConfigValue($key, false, $group));

                if (!array_key_exists($url, $endpoints)) {
                    $endpoints[$url] = array(
                        'envId' => $id,
                        'url'   => $url,
                    );
                }
            }
        } else {
            $endpoints[0] = $platform;
        }

        $this->response->data(['data' => array_values($endpoints)]);
    }

    /**
     * Gets instance types with its prices
     *
     * @param   string                  $cloudLocation
     * @param   string                  $url
     * @param   PlatformModuleInterface $pm
     * @param   string                  $platformName
     * @param   DateTime                $effectiveDate  optional The date on which prices should be applied
     * @param   Scalr_Environment       $env            optional
     * @return  array
     */
    private function getTypesWithPrices($cloudLocation, $url, $pm, $platformName, $effectiveDate = null, Scalr_Environment $env = null)
    {
        $typeNames = $pm->getInstanceTypes($env, $cloudLocation);

        $result = [
            'cloudLocation'     => $cloudLocation,
            'types'             => array_keys($typeNames),
            'url'               => $this->getContainer()->analytics->prices->normalizeUrl($url),
        ];

        $pricing = $this->getPlatformPricing($platformName, $result['cloudLocation'], $result['url'], $effectiveDate);

        if ($pricing['prices']) {
            foreach ($pricing['prices'] as $price) {
                if (false !== ($pos = array_search($price['type'], $result['types'])))
                    unset($result['types'][$pos]);
            }
        }

        foreach ($result['types'] as $type) {
            $pricing['prices'][] = [
                'type' => $type,
                'name' => (isset($typeNames[$type]) ? $typeNames[$type] : $type),
            ];
        }

        $result = array_merge($result, $pricing);

        unset($result['types']);

        //Prices should be ordered by name
        if (!empty($result['prices']) && is_array($result['prices'])) {
            usort($result['prices'], function ($a, $b) {
                if ($a['type'] == $b['type']) return 0;
                return ($a['type'] < $b['type']) ? -1 : 1;
            });
        }

        return $result;
    }

    /**
     * xGetPlatformInstanceTypesAction
     *
     * @param    string        $platform      The name of the cloud platform
     * @param    string        $cloudLocation The cloud location
     * @param    string        $envId         optional The identifier of the environment
     * @param    string        $effectiveDate optional The date on which prices should be applied YYYY-MM-DD
     * @throws   \Exception
     */
    public function xGetPlatformInstanceTypesAction($platform, $cloudLocation, $envId = null, $effectiveDate = null)
    {
        list($curDate, $effectiveDate) = $this->handleEffectiveDate($effectiveDate);

        $pm = PlatformFactory::NewPlatform($platform);
        $env = null;
        $url = '';

        if (!empty($envId)) {
            $env = Scalr_Environment::init()->loadById($envId);

            //TODO the key should be retrieved from the method which is provisioned by interface
            if (PlatformFactory::isOpenstack($platform)) {
                $key = $platform . '.' . OpenstackPlatformModule::KEYSTONE_URL;
            } else if (PlatformFactory::isCloudstack($platform)) {
                $key = $platform . '.' . CloudstackPlatformModule::API_URL;
            } else if ($platform == SERVER_PLATFORMS::EUCALYPTUS) {
                $key = EucalyptusPlatformModule::EC2_URL;
                $url = $this->getContainer()->analytics->prices->normalizeUrl($env->getPlatformConfigValue($key, false, $cloudLocation));
            } else {
                throw new Exception('This action is not yet supported for the specified cloud platform.');
            }

            if (empty($url)) {
                $url = $this->getContainer()->analytics->prices->normalizeUrl($env->getPlatformConfigValue($key));
            }
        }

        $result = $this->getTypesWithPrices($cloudLocation, $url, $pm, $platform, $effectiveDate, $env);

        $this->response->data(['data' => $result]);
    }

    /**
     * Handles effective date
     *
     * @param   string    $effectiveDate  Date YYYY-MM-DD
     * @return  array     Returns array of the DateTime objects looks like [cur-date, effective-date]
     */
    private function handleEffectiveDate($effectiveDate)
    {
        $curdate = new DateTime('now', new DateTimeZone('UTC'));

        if ($effectiveDate === null) {
            $effectiveDate = $curdate;
        } else {
            $effectiveDate = new DateTime($effectiveDate, new DateTimeZone('UTC'));
        }

        if ($effectiveDate < $curdate) {
            //We does not allow to change price on past date
            $effectiveDate = $curdate;
        }

        return [$curdate, $effectiveDate];
    }

    /**
     * Removes prices on the future effective date
     *
     * @param  string   $platform
     * @param  string   $cloudLocation
     * @param  string   $effectiveDate         The date when the prices will be applied
     * @param  string   $url                   optional
     */
    public function xDeleteAction($platform, $cloudLocation, $effectiveDate, $url = '')
    {
        list($curDate, $effectiveDate) = $this->handleEffectiveDate($effectiveDate);

        if ($effectiveDate <= $curDate) {
            throw new OutOfRangeException(sprintf("It is forbidden to remove prices either on the past or ongoing day."));
        }

        $service = $this->getContainer()->analytics->prices;

        $entity = PriceHistoryEntity::findOne([
            ['platform'      => $platform],
            ['url'           => $service->normalizeUrl($url) ?: ''],
            ['cloudLocation' => $cloudLocation],
            ['applied'       => $effectiveDate],
            ['accountId'     => $this->user->getAccountId() ?: 0]
        ]);

        if ($entity !== null) {
            $cadb = $this->getContainer()->cadb;
            try {
                $cadb->BeginTrans();

                $cadb->Execute("DELETE FROM `prices` WHERE `price_id` = " . $entity->qstr('priceId', $entity->priceId));

                $entity->delete();

                $cadb->CommitTrans();
            } catch (Exception $e) {
                $cadb->RollbackTrans();
                $this->response->failure(sprintf("Database error"));
                return;
            }

            $this->response->success('Prices have been successfully removed');
            return;
        }

        throw new NotFoundException("Could not find any price with specified parameters");
    }

    /**
     * xSavePriceAction
     *
     * @param  string   $platform              The cloud platform
     * @param  string   $cloudLocation         The cloud location
     * @param  JsonData $prices                Price list
     * @param  string   $url                   optional The url of the cloud
     * @param  string   $effectiveDate         optional The date when the prices will be applied
     * @param  boolean  $forbidAutomaticUpdate optional
     */
    public function xSavePriceAction($platform, $cloudLocation, JsonData $prices, $url = '', $effectiveDate = null, $forbidAutomaticUpdate = false)
    {
        list($curdate, $effectiveDate) = $this->handleEffectiveDate($effectiveDate);

        $service = $this->getContainer()->analytics->prices;

        $priceHistory = new PriceHistoryEntity();
        $priceHistory->platform = $platform;
        $priceHistory->cloudLocation = $cloudLocation;
        $priceHistory->url = $service->normalizeUrl($url);
        $priceHistory->accountId = $this->user->getAccountId();
        $priceHistory->applied = $effectiveDate;

        $this->getContainer()->analytics->prices->get($priceHistory);

        if ($priceHistory->priceId !== null) {
            //We have found recent prices
            if ($effectiveDate->format('Y-m-d') != $priceHistory->applied->format('Y-m-d')) {
                //We should generate a new version of the price
                $priceHistory->priceId = null;
                $priceHistory->applied = $effectiveDate;
            }
        }

        $details = new ArrayCollection();

        $found = [];

        foreach ($prices as $price) {
            if ($price['type'] && $price['priceLinux']) {
                $item = new PriceEntity();
                $item->instanceType = $price['type'];
                $item->os = PriceEntity::OS_LINUX;
                $item->name = $price['name'];
                $item->cost = floatval($price['priceLinux']);

                $found[$item->instanceType . '-' . $item->os] = $item;
                $details->append($item);
            }

            if ($price['type'] && $price['priceWindows']) {
                $item = new PriceEntity();
                $item->instanceType = $price['type'];
                $item->os = PriceEntity::OS_WINDOWS;
                $item->name = $price['name'];
                $item->cost = floatval($price['priceWindows']);

                $found[$item->instanceType . '-' . $item->os] = $item;
                $details->append($item);
            }
        }

        //Gets actual pricing on date from databse
        $collection = $this->getContainer()->analytics->prices->getActualPrices(
            $platform, $cloudLocation, $priceHistory->url, $priceHistory->applied
        );

        //Compares if some price has been changed.
        if (!$collection->count()) {
            //There aren't any prices yet. We need to save them.
            $bChanged = true;
        } else {
            $bChanged = false;

            //For each previous price compare difference with new one
            foreach ($collection as $priceEntity) {
                $key = $priceEntity->instanceType . '-' . $priceEntity->os;

                if (isset($found[$key])) {
                    if (abs($found[$key]->cost - $priceEntity->cost) > .0000009) {
                        unset($found[$key]);
                        $bChanged = true;
                        break;
                    }

                    unset($found[$key]);
                } else {
                    $bChanged = true;
                    break;
                }
            }

            //Some new price for new instance type is added while other prices reman untouched
            if (!$bChanged && count($found)) {
                foreach ($found as $priceEntity) {
                    //Zerro prices should not be taken into account if they don't exist before
                    if ($priceEntity->cost >= .000001) {
                        $bChanged = true;
                        break;
                    }
                }
            }
        }

        if ($bChanged) {
            //Saving actually only when there is some change
            $priceHistory->setDetails($details);

            $service->save($priceHistory);

            $this->getContainer()->analytics->events->fireChangeCloudPricingEvent($platform, $url);
        }

        if ($priceHistory->platform == SERVER_PLATFORMS::EC2) {
            SettingEntity::setValue(
                SettingEntity::ID_FORBID_AUTOMATIC_UPDATE_AWS_PRICES,
                $forbidAutomaticUpdate ? '1' : '0'
            );
        }

        $this->response->success('Prices have been updated');
    }
}
