<?php

namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject;
use DateTime;
use DateTimeZone;
use Exception;
use stdClass;
use Scalr\System\Zmq\Cron\AbstractTask;
use \Scalr_Db_Msr;
use \Scalr_Environment;
use \Scalr_Model;
use \Scalr_Role_Behavior;
use \Scalr_Role_DbMsrBehavior;
use \ROLE_BEHAVIORS;
use \DBFarm;
use \DBFarmRole;
use \SERVER_PLATFORMS;
use \FARM_STATUS;
use Scalr\Exception\NotApplicableException;


/**
 * DbMsrMaintenance task
 *
 * @author  N.V.
 */
class DbMsrMaintenance extends AbstractTask
{

    /**
     * {@inheritdoc}
     */
    public function enqueue()
    {
        $queue = new ArrayObject([]);

        $db = \Scalr::getDb();

        $rs = $db->Execute("
            SELECT id FROM farm_roles
            WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior IN (?,?,?,?))
        ",[
            ROLE_BEHAVIORS::POSTGRESQL,
            ROLE_BEHAVIORS::REDIS,
            ROLE_BEHAVIORS::MYSQL2,
            ROLE_BEHAVIORS::PERCONA
        ]);

        while ($row = $rs->FetchRow()) {
            $obj = new stdClass();

            $obj->id = $row["id"];

            $queue->append($obj);
        }

        if ($cnt = count($queue)) {
            $this->getLogger()->info("%d farm role%s with database behavior found", $cnt, ($cnt == 1 ? '' : 's'));
        }

        return $queue;
    }

    /**
     * {@inheritdoc}
     */
    public function worker($request)
    {
        //Warming up static DI cache
        \Scalr::getContainer()->warmup();

        try {
            $dbFarmRole = DBFarmRole::LoadByID($request->id);
            $dbFarm = $dbFarmRole->GetFarmObject();

            /* @var $env Scalr_Environment */
            $env = Scalr_Model::init(Scalr_Model::ENVIRONMENT)->loadById($dbFarm->EnvID);

            $tz = $env->getPlatformConfigValue(Scalr_Environment::SETTING_TIMEZONE);

            if (!$tz) {
                $tz = date_default_timezone_get();
            }

            $farmTz = $dbFarm->GetSetting(DBFarm::SETTING_TIMEZONE);

            if ($farmTz) {
                $tz = $farmTz;
            }

            //skip terminated farms
            if ($dbFarm->Status != FARM_STATUS::RUNNING) {
                return $request;
            }
        } catch (Exception $e) {
            $this->getLogger()->warn("Could not load farm role with id:%d, %s", $request->id, $e->getMessage());
            return $request;
        }

        $this->performDbMsrAction('BUNDLE', $dbFarmRole, $tz);

        $backupsNotSupported = in_array($dbFarmRole->Platform, array(
            SERVER_PLATFORMS::CLOUDSTACK,
            SERVER_PLATFORMS::IDCF
        ));

        if (!$backupsNotSupported) {
            $this->performDbMsrAction('BACKUP', $dbFarmRole, $tz);
        }

        return $request;
    }

    /**
     * Action on DB data
     *
     * @param string      $action      The action to do
     * @param \DBFarmRole $dbFarmRole  DBFarmRole object to do
     * @param string      $tz          Timezone
     */
    public function performDbMsrAction($action, DBFarmRole $dbFarmRole, $tz)
    {
        $timeouted = false;

        if ($dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_ENABLED")) && $dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_EVERY")) != 0) {
            if ($dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_IS_RUNNING")) == 1) {
                // Wait for timeout time * 2 (Example: NIVs problem with big mysql snapshots)
                // We must wait for running bundle process.
                $timeout = $dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_EVERY")) * (3600 * 2);
                $lastTs = $dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_RUNNING_TS"));
                if ($lastTs + $timeout < time()) {
                    $timeouted = true;
                }

                if ($timeouted) {
                    $dbFarmRole->SetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_IS_RUNNING"), 0, DBFarmRole::TYPE_LCL);
                }
            } else {
                //Check bundle window
                $period = $dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_EVERY"));
                $timeout = $period * 3600;
                $lastActionTime = $dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_LAST_TS"));

                $performAction = false;
                if ($period % 24 == 0) {
                    if ($lastActionTime) {
                        $days = $period / 24;

                        $dateTime = new DateTime(null, new DateTimeZone($tz));
                        $currentDate = (int) $dateTime->format("Ymd");

                        $dateTime->setTimestamp(strtotime("+{$days} day", $lastActionTime));
                        $nextDate = (int) $dateTime->format("Ymd");

                        if ($nextDate > $currentDate) {
                            return;
                        }
                    }

                    $pbwFrom = (int) (
                        $dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_TIMEFRAME_START_HH")) .
                        $dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_TIMEFRAME_START_MM"))
                    );

                    $pbwTo = (int) (
                        $dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_TIMEFRAME_END_HH")) .
                        $dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_TIMEFRAME_END_MM"))
                    );

                    if ($pbwFrom && $pbwTo) {
                        $dateTime = new DateTime(null, new DateTimeZone($tz));
                        $currentTime = (int) $dateTime->format("Hi");

                        if ($pbwFrom <= $currentTime && $pbwTo >= $currentTime) {
                            $performAction = true;
                        }
                    } else {
                        $performAction = true;
                    }
                } else {
                    //Check timeout
                    if ($lastActionTime+$timeout < time()) {
                        $performAction = true;
                    }
                }

                try {
                    if ($performAction) {
                        $behavior = Scalr_Role_Behavior::loadByName($dbFarmRole->GetRoleObject()->getDbMsrBehavior());

                        if ($action == 'BUNDLE') {
                            $behavior->createDataBundle($dbFarmRole, array(
                                'compressor' => $dbFarmRole->GetSetting(Scalr_Role_DbMsrBehavior::ROLE_DATA_BUNDLE_COMPRESSION),
                                'useSlave'   => $dbFarmRole->GetSetting(Scalr_Role_DbMsrBehavior::ROLE_DATA_BUNDLE_USE_SLAVE)
                            ));
                        }

                        if ($action == 'BACKUP') {
                            $behavior->createBackup($dbFarmRole);
                        }
                    }
                } catch (NotApplicableException $e) {
                    //No suitable server
                }
            }
        }
    }
}
