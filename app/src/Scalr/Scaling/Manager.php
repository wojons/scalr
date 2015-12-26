<?php

use Scalr\Model\Entity;

class Scalr_Scaling_Manager
{
    private $db;

    /**
     * @var Scalr_Scaling_FarmRoleMetric[]
     */
    private $farmRoleMetrics;
    private $dbFarmRole;

    public $decisonInfo;

    public $logger;

    /**
     * Constructor
     * @param $DBFarmRole
     * @return void
     */
    function __construct(DBFarmRole $DBFarmRole)
    {
        $this->db = \Scalr::getDb();
        $this->dbFarmRole = $DBFarmRole;
        $this->logger = \Scalr::getContainer()->logger(__CLASS__);

        $role_metrics = $this->db->Execute("SELECT id, metric_id FROM farm_role_scaling_metrics WHERE farm_roleid = ?", array($this->dbFarmRole->ID));
        $this->farmRoleMetrics = array();
        while ($role_metric = $role_metrics->FetchRow()) {
            if ($role_metric['metric_id'])
                $this->farmRoleMetrics[$role_metric['metric_id']] = Scalr_Model::init(Scalr_Model::SCALING_FARM_ROLE_METRIC)->loadById($role_metric['id']);
        }
    }

    function setFarmRoleMetrics($metrics)
    {
        foreach ($this->farmRoleMetrics as $id => $farmRoleMetric) {
            if (!$metrics[$farmRoleMetric->metricId]) {
                $farmRoleMetric->delete();
                unset($this->farmRoleMetrics[$farmRoleMetric->metricId]);
            }
        }

        foreach ($metrics as $metric_id => $metric_settings) {
            if (!is_array($metric_settings))
                continue;

            if (!$this->farmRoleMetrics[$metric_id]) {
                $this->farmRoleMetrics[$metric_id] = Scalr_Model::init(Scalr_Model::SCALING_FARM_ROLE_METRIC);
                $this->farmRoleMetrics[$metric_id]->metricId = $metric_id;
                $this->farmRoleMetrics[$metric_id]->farmRoleId = $this->dbFarmRole->ID;
            }

            $this->farmRoleMetrics[$metric_id]->clearSettings();
            $this->farmRoleMetrics[$metric_id]->setSettings($metric_settings);
            $this->farmRoleMetrics[$metric_id]->save();
        }
    }

    function getFarmRoleMetrics()
    {
        return $this->farmRoleMetrics;
    }

    /**
     * Makes decision on farm basic scaling settings
     *
     * @param   string  $scalingMetricDecision          optional Decision taken on metrics
     * @param   int     $scalingMetricInstancesCount    optional Scaling amount
     *
     * @return  string  Returns resulting decision
     */
    public function getFinalDecision($scalingMetricDecision = null, $scalingMetricInstancesCount = null)
    {
        $isDbMsr = $this->dbFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MYSQL) ||
            $this->dbFarmRole->GetRoleObject()->getDbMsrBehavior();

        $needOneByOneLaunch = $this->dbFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::RABBITMQ) ||
            $this->dbFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MONGODB);

        // Check do we need upscale to min instances count
        $roleTotalInstances = $this->dbFarmRole->GetRunningInstancesCount()+$this->dbFarmRole->GetPendingInstancesCount();

        // Need to check Date&Time based scaling. Otherwise Scalr downscale role every time.
        if (isset($scalingMetricInstancesCount)) {
            $minInstances = $maxInstances = $scalingMetricInstancesCount;
        } else {
            $maxInstances = $this->dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MAX_INSTANCES);
            $minInstances = $this->dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MIN_INSTANCES);
        }

        if ($roleTotalInstances < $minInstances) {
            if ($needOneByOneLaunch) {
                $pendingTerminateInstances = count($this->dbFarmRole->GetServersByFilter(array('status' => SERVER_STATUS::PENDING_TERMINATE)));
                // If we launching DbMSR instances. Master should be running.
                if ($this->dbFarmRole->GetPendingInstancesCount() == 0 && !$pendingTerminateInstances) {
                    $this->logger->info(_("Increasing number of running instances to fit min instances setting"));
                    $this->decisonInfo = '2';
                    return Scalr_Scaling_Decision::UPSCALE;
                } else {
                    $this->logger->info(_("Found servers in Pending or PendingTerminate state. Waiting..."));
                    return Scalr_Scaling_Decision::NOOP;
                }
            } elseif ($isDbMsr) {
                // If we launching DbMSR instances. Master should be running.
                if ($this->dbFarmRole->GetRunningInstancesCount() > 0 || $this->dbFarmRole->GetPendingInstancesCount() == 0) {
                    $this->logger->info(_("Increasing number of running instances to fit min instances setting"));
                    $this->decisonInfo = '3';
                    return Scalr_Scaling_Decision::UPSCALE;
                } else {
                    $this->logger->info(_("Waiting for running master"));
                    return Scalr_Scaling_Decision::NOOP;
                }
            } else {
                $this->logger->info(_("Increasing number of running instances to fit min instances setting"));
                $this->decisonInfo = '4';
                return Scalr_Scaling_Decision::UPSCALE;
            }
        } elseif ($maxInstances && $this->dbFarmRole->GetRunningInstancesCount() > $maxInstances) {
            $this->logger->info(_("Decreasing number of running instances to fit max instances setting ({$scalingMetricInstancesCount})"));
            $this->decisonInfo = '6';
            return Scalr_Scaling_Decision::DOWNSCALE;
        }

        return isset($scalingMetricDecision) ? $scalingMetricDecision : Scalr_Scaling_Decision::NOOP;
    }

    /**
     * Logging information about decision
     *
     * @param   string  $scalingMetricDecision           Scaling decision
     * @param   string  $scalingMetricName               Name of metric by which a decision was made
     * @param   mixed   $lastValue              optional Last sensor value
     */
    public function logDecisionInfo($scalingMetricDecision, $scalingMetricName, $lastValue = null)
    {
        if ($scalingMetricDecision !== Scalr_Scaling_Decision::NOOP) {
            \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->info(new FarmLogMessage(
                $this->dbFarmRole->FarmID,
                sprintf("%s: Role '%s' on farm '%s'. Metric name: %s. Last metric value: %s.",
                    $scalingMetricDecision,
                    $this->dbFarmRole->Alias ? $this->dbFarmRole->Alias : $this->dbFarmRole->GetRoleObject()->name,
                    $this->dbFarmRole->GetFarmObject()->Name,
                    $scalingMetricName,
                    $lastValue
                )
            ));

            $this->logger->info(sprintf(_("Metric: %s. Decision: %s. Last value: %s"),
                    $scalingMetricName, $scalingMetricDecision, $lastValue)
            );
        }
    }

    /**
     * Makes a decision to scale farm
     *
     * @return Scalr_Scaling_Decision
     */
    function makeScalingDecision()
    {
        /**
         * Base Scaling
         */
        foreach (Scalr_Role_Behavior::getListForFarmRole($this->dbFarmRole) as $behavior) {
            $result = $behavior->makeUpscaleDecision($this->dbFarmRole);
            if ($result === false)
                continue;
            else
                return $result;
        }

        $farmPendingInstances = $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE farm_id=? AND status IN (?,?,?)",
            array($this->dbFarmRole->FarmID, SERVER_STATUS::PENDING, SERVER_STATUS::INIT, SERVER_STATUS::PENDING_LAUNCH)
        );

        if ($this->dbFarmRole->GetFarmObject()->RolesLaunchOrder == 1 && $farmPendingInstances > 0) {
            if ($this->dbFarmRole->GetRunningInstancesCount() == 0) {
                $this->logger->info("{$farmPendingInstances} instances in pending state. Launch roles one-by-one. Waiting...");
                return Scalr_Scaling_Decision::STOP_SCALING;
            }
        }

        $scalingMetricDecision = null;
        $scalingMetricInstancesCount = null;

        $this->logger->info(sprintf(_("%s scaling rules configured"),
            count($this->farmRoleMetrics)
        ));

        if (isset($this->farmRoleMetrics[Entity\ScalingMetric::METRIC_DATE_AND_TIME_ID])) {
            $dateAndTimeMetric = $this->farmRoleMetrics[Entity\ScalingMetric::METRIC_DATE_AND_TIME_ID];

            try {
                $scalingMetricDecision = $dateAndTimeMetric->getScalingDecision();
            } catch (Exception $e) {
                $this->logger->error("Scaling error in deciding metric '{$dateAndTimeMetric->getMetric()->name}' for farm role '{$this->dbFarmRole->FarmID}:{$this->dbFarmRole->Alias}': {$e->getMessage()}");
            }

            $scalingMetricName = $dateAndTimeMetric->getMetric()->name;

            $scalingMetricInstancesCount = $dateAndTimeMetric->instancesNumber;

            $this->decisonInfo = '1';

            if ($scalingMetricDecision !== Scalr_Scaling_Decision::NOOP) {
                $this->logDecisionInfo($scalingMetricDecision, $scalingMetricName, $dateAndTimeMetric->lastValue);
                return $scalingMetricDecision;
            }
        }

        /**
         * Metrics scaling
         */
        $farmRoleMetrics = array_diff_key($this->farmRoleMetrics, [Entity\ScalingMetric::METRIC_DATE_AND_TIME_ID => null]);
        if (!empty($farmRoleMetrics)) {
            $checkAllMetrics = $this->dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_DOWN_ONLY_IF_ALL_METRICS_TRUE);
            $variousDecisions = false;

            /* @var $farmRoleMetric Scalr_Scaling_FarmRoleMetric */
            foreach ($farmRoleMetrics as $farmRoleMetric) {
                try {
                    $newDecision = $farmRoleMetric->getScalingDecision();
                } catch (Exception $e) {
                    $this->logger->error("Scaling error in deciding metric '{$farmRoleMetric->getMetric()->name}' for farm role '{$this->dbFarmRole->FarmID}:{$this->dbFarmRole->Alias}': {$e->getMessage()}");
                    continue;
                }

                if (isset($scalingMetricDecision)) {
                    if ($newDecision != $scalingMetricDecision) {
                        $variousDecisions = true;
                    }
                }

                $scalingMetricDecision = $newDecision;

                $scalingMetricName = $farmRoleMetric->getMetric()->name;

                $this->decisonInfo = '1';

                switch ($scalingMetricDecision) {
                    case Scalr_Scaling_Decision::NOOP:
                        continue;

                    case Scalr_Scaling_Decision::DOWNSCALE:
                        if (!$checkAllMetrics) {
                            break 2;
                        }

                        continue;

                    case Scalr_Scaling_Decision::UPSCALE:
                        break 2;
                }
            }

            if (isset($scalingMetricDecision) && !($scalingMetricDecision == Scalr_Scaling_Decision::DOWNSCALE && $checkAllMetrics && $variousDecisions)) {
                $this->logDecisionInfo($scalingMetricDecision, $scalingMetricName, isset($farmRoleMetric) ? $farmRoleMetric->lastValue : null);
            } else {
                $scalingMetricDecision = Scalr_Scaling_Decision::NOOP;
            }
        }

        return $this->getFinalDecision($scalingMetricDecision, $scalingMetricInstancesCount);
    }
}
