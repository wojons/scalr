<?php

namespace Scalr\System\Zmq\Cron\Task
{
    use Scalr\Farm\Role\FarmRoleStorageConfig;
    use Scalr\Stats\CostAnalytics\Entity\UsageItemEntity;
    use Scalr\Stats\CostAnalytics\Entity\UsageTypeEntity;
    use Scalr\System\Zmq\Cron\Task\AnalyticsDemo\stdCc;
    use Scalr\System\Zmq\Cron\Task\AnalyticsDemo\stdEnv;
    use Scalr\System\Zmq\Cron\Task\AnalyticsDemo\stdFarm;
    use Scalr\System\Zmq\Cron\Task\AnalyticsDemo\stdFarmRole;
    use Scalr\System\Zmq\Cron\Task\AnalyticsDemo\stdPricing;
    use Scalr\System\Zmq\Cron\Task\AnalyticsDemo\stdProject;
    use ArrayObject;
    use DateTime;
    use DateTimeZone;
    use Scalr\Stats\CostAnalytics\Entity\PriceEntity;
    use Scalr\Stats\CostAnalytics\Entity\QuarterlyBudgetEntity;
    use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
    use Scalr\Stats\CostAnalytics\Entity\UsageHourlyEntity;
    use Scalr\Stats\CostAnalytics\Quarters;
    use Scalr\System\Zmq\Cron\AbstractTask;
    use Scalr\Model\Entity;

    /**
     * AnalyticsDemo task
     *
     * @author  Vitaliy Demidov <vitaliy@scalr.com>
     * @author  N.V.
     * @since   5.3.1
     */
    class AnalyticsDemo extends AbstractTask
    {

        const PAST_HOURS_INIT = 0;

        /**
         * {@inheritdoc}
         * @see \Scalr\System\Zmq\Cron\AbstractTask::enqueue
         */
        public function enqueue()
        {
            $logger = $this->getLogger();
            if (!\Scalr::getContainer()->analytics->enabled) {
                $logger->info("CA has not been enabled in config!\n");
            }

            $db = \Scalr::getDb();
            $cadb = \Scalr::getContainer()->cadb;

            $pricing = new stdPricing();
            $quarters = new Quarters(SettingEntity::getQuarters());

            $price = [];

            $bandwidthItems = [
                'USE1-APN1-AWS', 'USE1-APS1-AWS', 'USE1-APS2-AWS',
                'USE1-CloudFront', 'USE1-EU-AWS', 'USE1-SAE1-AWS',
                'USE1-USW1-AWS ', 'USE1-USW2-AWS'
            ];

            $usageTypes = [
                UsageTypeEntity::NAME_COMPUTE_BOX_USAGE, UsageTypeEntity::NAME_STORAGE_EBS,
                UsageTypeEntity::NAME_STORAGE_EBS_IO, UsageTypeEntity::NAME_STORAGE_EBS_IOPS,
                UsageTypeEntity::NAME_BANDWIDTH_REGIONAL, UsageTypeEntity::NAME_BANDWIDTH_IN,
                UsageTypeEntity::NAME_BANDWIDTH_OUT
            ];

            $logger->info('Started AnalyticsDemo process');

            $tzUtc = new DateTimeZone('UTC');

            /* @var $projects stdProject[] */
            $projects = [];

            /* @var $ccs stdCc[] */
            $ccs = [];

            /* @var $farms stdFarm[] */
            $farms = [];

            /* @var $environments stdEnv[] */
            $environments = [];

            /* @var $farmRoles stdFarmRole[] */
            //$farmRoles = [];

            //Analytics container
            $analytics = \Scalr::getContainer()->analytics;

            $logger->debug('CC & PROJECTS ---');

            foreach ($analytics->ccs->all(true) as $cc) {
                /* @var $cc \Scalr\Stats\CostAnalytics\Entity\CostCentreEntity */
                $co = new stdCc();
                $co->cc = $cc;

                $ccs[$cc->ccId] = $co;

                $logger->debug("Cost center: '%s'", $cc->name);

                foreach ($cc->getProjects() as $project) {
                    /* @var $project \Scalr\Stats\CostAnalytics\Entity\ProjectEntity */
                    $project->loadProperties();

                    $po = new stdProject();
                    $po->project = $project;
                    $po->cc = $co;

                    $projects[$project->projectId] = $po;
                    $logger->debug("-- Project: '%s'", $project->name);
                }
            }

            //Ordering cost centers
            $number = 0;
            foreach ($ccs as $obj) {
                $obj->number = $number++;
            }
            //Ordering projects
            $number = 0;
            foreach ($projects as $obj) {
                $obj->number = $number++;
            }

            $logger->debug("FARMS ---");

            $pastIterations = static::PAST_HOURS_INIT;

            //Current time
            $start = new DateTime('now', $tzUtc);
            $dt = clone $start;
            do {
                $timestamp = $dt->format('Y-m-d H:00:00');
                $period = $quarters->getPeriodForDate($dt->format('Y-m-d'));

                $logger->info("Processing time:%s, year:%d, quarter:%d", $timestamp, $period->year, $period->quarter);

                //Gets farms for each project
                foreach ($projects as $po) {
                    foreach ($analytics->projects->getFarmsList($po->project->projectId) as $farmId => $farmName) {
                        if (!isset($farms[$farmId])) {
                            $fo = new stdFarm();
                            $fo->farm = \DBFarm::LoadByID($farmId);
                            $fo->project = $po;
                            $fo->cc = $po->cc;

                            //$po->farms[] = $fo;
                            $farms[$farmId] = $fo;

                            if (!isset($environments[$fo->farm->EnvID])) {
                                $eo = new stdEnv();
                                $eo->env = $fo->farm->getEnvironmentObject();
                                //$eo->farms = [$farmId => $fo];
                                $environments[$fo->farm->EnvID] = $eo;
                                $fo->env = $eo;
                            } else {
                                //$environments[$fo->farm->EnvID]->farms[$farmId] = $fo;
                                $fo->env = $environments[$fo->farm->EnvID];
                            }

                            $fo->farmRoles = [];

                            foreach ($fo->farm->GetFarmRoles() as $farmRole) {
                                $fro = new stdFarmRole();
                                $fro->farmRole = $farmRole;
                                $fro->farm = $fo;
                                $fro->min = $farmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MIN_INSTANCES);
                                $fro->max = $farmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MAX_INSTANCES);

                                $fo->farmRoles[$farmRole->ID] = $fro;
                                //$farmRoles[$farmRole->ID] = $fro;
                            }
                        } else {
                            $fo = $farms[$farmId];
                        }

                        $logger->debug(
                            "Farm:'%s':%d from Env:'%s':%d corresponds to Project:'%s' -> CC:'%s'",
                            $fo->farm->Name, $fo->farm->ID,
                            $fo->farm->getEnvironmentObject()->name, $fo->farm->EnvID,
                            $po->project->name, $po->cc->cc->name
                        );

                        foreach ($fo->farmRoles as $fro) {
                            /* @var $fro stdFarmRole */

                            foreach ($usageTypes as $usageType) {
                                $displayName = null;

                                if ($usageType === UsageTypeEntity::NAME_COMPUTE_BOX_USAGE) {
                                    $countInstances = rand(max(1, floor($fro->max * 0.7)), min((int) $fro->max, 2));

                                    $cost = $pricing->getPrice(
                                        $dt,
                                        $fro->farmRole->Platform,
                                        $fro->farmRole->CloudLocation,
                                        $fro->getInstanceType(),
                                        $fo->env->getUrl($fro->farmRole->Platform),
                                        PriceEntity::OS_LINUX
                                    );

                                    $costDistType = UsageTypeEntity::COST_DISTR_TYPE_COMPUTE;
                                    $item = $fro->getInstanceType();
                                    $displayName = 'Compute Instances';
                                } else if ($fro->farmRole->Platform == \SERVER_PLATFORMS::EC2) {
                                    if ($usageType === UsageTypeEntity::NAME_STORAGE_EBS || $usageType === UsageTypeEntity::NAME_STORAGE_EBS_IO || $usageType === UsageTypeEntity::NAME_STORAGE_EBS_IOPS) {
                                        $costDistType = UsageTypeEntity::COST_DISTR_TYPE_STORAGE;
                                        $configs = FarmRoleStorageConfig::getByFarmRole($fro->farmRole);

                                        $config = reset($configs);
                                        /* @var $config FarmRoleStorageConfig */
                                        if (!empty($config->settings[FarmRoleStorageConfig::SETTING_EBS_TYPE])) {
                                            $item = $config->settings[FarmRoleStorageConfig::SETTING_EBS_TYPE];

                                            if (!isset($price[$item])) {
                                                $price[$item] = rand(0.01, 0.09);
                                            }

                                            $countInstances = rand(100, 10000);
                                        } else {
                                            continue;
                                        }
                                    } else if ($usageType === UsageTypeEntity::NAME_BANDWIDTH_REGIONAL || $usageType === UsageTypeEntity::NAME_BANDWIDTH_IN || $usageType === UsageTypeEntity::NAME_BANDWIDTH_OUT) {
                                        $costDistType = UsageTypeEntity::COST_DISTR_TYPE_BANDWIDTH;
                                        $countInstances = rand(0, 10000);
                                        $key = array_rand($bandwidthItems);
                                        $item = $bandwidthItems[$key];
                                        $price[$item] = rand(0.01, 0.09);
                                    }

                                    $cost = $price[$item];
                                } else {
                                    continue;
                                }

                                $type = $usageType;

                                $usageTypeEntity = UsageTypeEntity::findOne([
                                    ['costDistrType' => $costDistType],
                                    ['name'          => $type]
                                ]);
                                /* @var $usageTypeEntity UsageTypeEntity */

                                if ($usageTypeEntity === null) {
                                    $usageTypeEntity = new UsageTypeEntity();
                                    $usageTypeEntity->costDistrType = $costDistType;
                                    $usageTypeEntity->displayName = $displayName;
                                    $usageTypeEntity->name = $type;
                                    $usageTypeEntity->save();
                                }

                                $usageItemEntity = UsageItemEntity::findOne([['usageType' => $usageTypeEntity->id], ['name' => $item]]);
                                /* @var $usageItemEntity UsageItemEntity */

                                if ($usageItemEntity === null) {
                                    $usageItemEntity = new UsageItemEntity();
                                    $usageItemEntity->usageType = $usageTypeEntity->id;
                                    $usageItemEntity->name = $item;
                                    $usageItemEntity->save();
                                }

                                //Hourly usage
                                $rec = new UsageHourlyEntity();
                                $rec->usageId = \Scalr::GenerateUID();
                                $rec->accountId = $fro->farm->farm->ClientID;
                                $rec->ccId = $po->cc->cc->ccId;
                                $rec->projectId = $po->project->projectId;
                                $rec->cloudLocation = $fro->farmRole->CloudLocation;
                                $rec->dtime = new DateTime($timestamp, $tzUtc);
                                $rec->envId = $fo->farm->EnvID;
                                $rec->farmId = $fo->farm->ID;
                                $rec->farmRoleId = $fro->farmRole->ID;
                                $rec->usageItem = $usageItemEntity->id;
                                $rec->platform = $fro->farmRole->Platform;
                                $rec->url = $fo->env->getUrl($fro->farmRole->Platform);
                                $rec->os = PriceEntity::OS_LINUX;
                                $rec->num = $countInstances;
                                $rec->cost = $cost * $countInstances;

                                $rec->save();

                                $logger->log(
                                    (static::PAST_HOURS_INIT > 0 ? 'DEBUG' : 'INFO'),
                                    "-- role:'%s':%d platform:%s, min:%d - max:%d, cloudLocation:'%s', usageItem:'%s', "
                                    . "cost:%0.4f * %d = %0.3f",
                                    $fro->farmRole->Alias, $fro->farmRole->ID, $fro->farmRole->Platform,
                                    $fro->min, $fro->max,
                                    $fro->farmRole->CloudLocation,
                                    $usageItemEntity->id,
                                    $cost, $countInstances,
                                    $rec->cost
                                );

                                //Update Daily table
                                $cadb->Execute("
                                INSERT usage_d
                                SET date = ?,
                                    platform = ?,
                                    cc_id = UNHEX(?),
                                    project_id = UNHEX(?),
                                    farm_id = ?,
                                    env_id = ?,
                                    cost = ?
                                ON DUPLICATE KEY UPDATE cost = cost + ?
                            ", [
                                    $rec->dtime->format('Y-m-d'),
                                    $rec->platform,
                                    ($rec->ccId ? str_replace('-', '', $rec->ccId) : '00000000-0000-0000-0000-000000000000'),
                                    ($rec->projectId ? str_replace('-', '', $rec->projectId) : '00000000-0000-0000-0000-000000000000'),
                                    ($rec->farmId ? $rec->farmId : 0),
                                    ($rec->envId ? $rec->envId : 0),
                                    $rec->cost,
                                    $rec->cost,
                                ]);

                                //Updates Quarterly Budget
                                if ($rec->ccId) {
                                    $cadb->Execute("
                                    INSERT quarterly_budget
                                    SET year = ?,
                                        subject_type = ?,
                                        subject_id = UNHEX(?),
                                        quarter = ?,
                                        budget = 1000,
                                        cumulativespend = ?
                                    ON DUPLICATE KEY UPDATE cumulativespend = cumulativespend + ?
                                ", [
                                        $period->year,
                                        QuarterlyBudgetEntity::SUBJECT_TYPE_CC,
                                        str_replace('-', '', $rec->ccId),
                                        $period->quarter,
                                        $rec->cost,
                                        $rec->cost,
                                    ]);
                                }

                                if ($rec->projectId) {
                                    $cadb->Execute("
                                    INSERT quarterly_budget
                                    SET year = ?,
                                        subject_type = ?,
                                        subject_id = UNHEX(?),
                                        quarter = ?,
                                        budget = 1000,
                                        cumulativespend = ?
                                    ON DUPLICATE KEY UPDATE cumulativespend = cumulativespend + ?
                                ", [
                                        $period->year,
                                        QuarterlyBudgetEntity::SUBJECT_TYPE_PROJECT,
                                        str_replace('-', '', $rec->projectId),
                                        $period->quarter,
                                        $rec->cost,
                                        $rec->cost,
                                    ]);
                                }
                            }
                        }

                        unset($fo);
                    }
                }

                $dt->modify('-1 hour');
            } while ($pastIterations-- > 0);

            $dt = clone $start;
            $start->modify(sprintf("-%d hour", static::PAST_HOURS_INIT));
            $start->setTime(0, 0, 0);

            $date = $dt->format('Y-m-d');
            $hours = (int) $dt->format('H');

            do {
                $cadb->Execute("
                    INSERT INTO `farm_usage_d` (
                        `account_id`,
                        `farm_role_id`,
                        `usage_item`,
                        `cc_id`,
                        `project_id`,
                        `date`,
                        `platform`,
                        `cloud_location`,
                        `env_id`,
                        `farm_id`,
                        `role_id`,
                        `cost`,
                        `min_usage`,
                        `max_usage`,
                        `usage_hours`,
                        `working_hours`)
                    SELECT
                        `account_id`,
                        IFNULL(`farm_role_id`, 0) `farm_role_id`,
                        `usage_item`,
                        IFNULL(`cc_id`, '') `cc_id`,
                        IFNULL(`project_id`, '') `project_id`,
                        ? `date`,
                        `platform`,
                        `cloud_location`,
                        IFNULL(`env_id`, 0) `env_id`,
                        IFNULL(`farm_id`, 0) `farm_id`,
                        IFNULL(`role_id`, 0) `role_id`,
                        SUM(`cost`) `cost`,
                        (CASE WHEN COUNT(`dtime`) >= ? THEN MIN(`num`) ELSE 0 END) `min_usage`,
                        MAX(`num`) `max_usage`,
                        SUM(`num`) `usage_hours`,
                        COUNT(`dtime`) `working_hours`
                    FROM `usage_h` `uh`
                    WHERE `uh`.`dtime` BETWEEN ? AND ?
                    AND `uh`.`farm_id` > 0
                    AND `uh`.`farm_role_id` > 0
                    GROUP BY `uh`.`account_id` , `uh`.`farm_role_id` , `uh`.`usage_item`
                    ON DUPLICATE KEY UPDATE
                        `cost` = VALUES(`cost`),
                        `min_usage` = VALUES(`min_usage`),
                        `max_usage` = VALUES(`max_usage`),
                        `usage_hours` = VALUES(`usage_hours`),
                        `working_hours` = VALUES(`working_hours`)
                ", ["{$date} 00:00:00", $hours, "{$date} 00:00:00", "{$date} 23:00:00"]);

                $dt->modify('-1 day');

                $date = $dt->format('Y-m-d');

                $hours = 24;
            } while ($dt >= $start);

            $logger->info("Finished AnalyticsDemo process");
            $logger->info("Memory usage: %0.3f Mb", memory_get_usage() / 1024 / 1024);

            return new ArrayObject();
        }

        /**
         * {@inheritdoc}
         * @see \Scalr\System\Zmq\Cron\AbstractTask::worker
         */
        public function worker($request)
        {
            return $request;
        }

        /**
         * {@inheritdoc}
         * @see \Scalr\System\Zmq\Cron\AbstractTask::config()
         */
        public function config()
        {
            $config = parent::config();

            if ($config->daemon) {
                //Report a warning to log
                trigger_error(sprintf("Demonized mode is not allowed for '%s' job.", $this->name), E_USER_WARNING);

                //Forces normal mode
                $config->daemon = false;
            }

            if ($config->workers != 1) {
                //It cannot be performed through ZMQ MDP as execution time is more than heartbeat
                trigger_error(sprintf("It is allowed only one worker for the '%s' job.", $this->name), E_USER_WARNING);
                $config->workers = 1;
            }

            return $config;
        }
    }
}

namespace Scalr\System\Zmq\Cron\Task\AnalyticsDemo
{

    use DateTime;
    use Scalr\Model\Entity\CloudLocation;
    use Scalr\Model\Entity\FarmRoleSetting;
    use Scalr\Modules\PlatformFactory;
    use Scalr\Modules\Platforms\Cloudstack\CloudstackPlatformModule;
    use Scalr\Modules\Platforms\Openstack\OpenstackPlatformModule;
    use Scalr\Model\Entity\CloudCredentialsProperty;

    class stdPricing extends \stdClass
    {
        private $cache;
        private $cadb;

        public function __construct()
        {
            $this->cadb = \Scalr::getContainer()->cadb;
        }

        public function getPrice(DateTime $applied, $platform, $cloudLocation, $instanceType, $url= '', $os = 0)
        {
            $key = sprintf('%s,%s,%s,%s,%s', $instanceType, $applied->format('Y-m-d'), $platform, $cloudLocation, $url);

            if (!isset($this->cache[$key])) {
                $this->cache[$key] = $this->cadb->GetOne("
                SELECT p.cost
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
                WHERE ph.account_id = ? AND p2.price_id IS NULL
                AND ph.platform = ?
                AND ph.cloud_location = ?
                AND ph.url = ?
                AND ph.applied <= ?
                AND p.instance_type = ?
                AND p.os = ?
                LIMIT 1
            ", [
                    $applied->format('Y-m-d'),
                    0,
                    $platform,
                    $cloudLocation,
                    $url,
                    $applied->format('Y-m-d'),
                    $instanceType,
                    $os
                ]);

                if (!$this->cache[$key] || $this->cache[$key] <= 0.0001) {
                    $this->cache[$key] = .0123;
                }
            }

            return $this->cache[$key];
        }
    }

    class stdProject extends \stdClass
    {
        /**
         * @var \Scalr\Stats\CostAnalytics\Entity\ProjectEntity
         */
        public $project;

        /**
         * @var stdCc
         */
        public $cc;

        /**
         * @var stdFarm[]
         */
        public $farms;

        /**
         * @var int
         */
        public $number;
    }

    class stdCc extends \stdClass
    {
        /**
         * @var \Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
         */
        public $cc;

        /**
         * @var int
         */
        public $number;
    }

    class stdFarm extends \stdClass
    {
        /**
         * @var \DBFarm
         */
        public $farm;

        /**
         * @var stdProject
         */
        public $project;

        /**
         * @var stdCc
         */
        public $cc;

        /**
         * @var stdEnv;
         */
        public $env;

        /**
         * @var stdFarmRole[]
         */
        public $farmRoles;

    }

    class stdFarmRole extends \stdClass
    {
        /**
         * @var \DBFarmRole
         */
        public $farmRole;

        /**
         * @var stdFarm
         */
        public $farm;

        /**
         * @var int
         */
        public $min;

        /**
         * @var int
         */
        public $max;

        /**
         * @var string
         */
        private $instanceType;

        /**
         * Gets instanceType
         *
         * @return  string
         */
        public function getInstanceType()
        {
            if ($this->instanceType === null) {
                $this->instanceType = $this->farmRole->GetSetting(FarmRoleSetting::INSTANCE_TYPE);
            }

            return $this->instanceType;
        }
    }

    class stdEnv extends \stdClass
    {
        /**
         * @var \Scalr_Environment
         */
        public $env;

        /**
         * @var stdFarm[]
         */
        public $farms;

        /**
         * List of url for an each platform
         * @var array
         */
        public $aUrl;

        /**
         * Gets a normalized url for an each platform
         *
         * @param    string $platform Cloud platform
         * @return   string Returns url
         */
        public function getUrl($platform)
        {
            if (!isset($this->aUrl[$platform])) {
                if ($platform == \SERVER_PLATFORMS::EC2 || $platform == \SERVER_PLATFORMS::GCE || $platform == \SERVER_PLATFORMS::AZURE) {
                    $value = '';
                } else if (PlatformFactory::isOpenstack($platform)) {
                    $value = CloudLocation::normalizeUrl(
                        $this->env->keychain($platform)->properties[CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL]
                    );
                } else if (PlatformFactory::isCloudstack($platform)) {
                    $value = CloudLocation::normalizeUrl(
                        $this->env->keychain($platform)->properties[CloudCredentialsProperty::CLOUDSTACK_API_URL]
                    );
                }

                $this->aUrl[$platform] = $value;
            }

            return $this->aUrl[$platform];
        }
    }
}
