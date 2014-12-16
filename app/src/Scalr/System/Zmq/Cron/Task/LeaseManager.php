<?php
namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject, Exception, DateTime, DateTimeZone, DateInterval, stdClass;
use Scalr\System\Zmq\Cron\AbstractTask;
use \FarmTerminatedEvent;
use \Scalr_Governance;
use \Scalr_Util_DateTime;
use \Scalr_Account_User;
use \DBFarm;
use \FARM_STATUS;

/**
 * LeaseManager
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0.1 (02.12.2014)
 */
class LeaseManager extends AbstractTask
{
    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::enqueue()
     */
    public function enqueue()
    {
        $queue = new ArrayObject([]);

        $db = \Scalr::getDb();

        $this->log('INFO', "Fetching farms...");

        $farms = [];

        $rs = $db->Execute("
            SELECT env_id, value FROM governance
            WHERE enabled = 1 AND name = ?
        ", [Scalr_Governance::GENERAL_LEASE]);

        while ($env = $rs->FetchRow()) {
            $env['value'] = json_decode($env['value'], true);

            $period = 0;

            if (is_array($env['value']['notifications'])) {
                foreach ($env['value']['notifications'] as $notif) {
                    if ($notif['period'] > $period)
                        $period = $notif['period'];
                }

                $dt = new DateTime();
                $dt->sub(new DateInterval('P' . $period . 'D'));

                $fs = $db->GetAll("
                    SELECT fs.farmid, f.status
                    FROM farm_settings fs
                    JOIN farms f ON f.id = fs.farmid
                    WHERE fs.name = ? AND f.status = ? AND f.env_id = ? AND fs.value > ?
                ", [
                    DBFarm::SETTING_LEASE_TERMINATE_DATE,
                    FARM_STATUS::RUNNING,
                    $env['env_id'],
                    $dt->format('Y-m-d H:i:s')
                ]);

                foreach ($fs as $f) {
                    if (!isset($farms[$f['farmid']])) {
                        $farms[$f['farmid']] = true;

                        $obj = new stdClass();
                        $obj->farmId = $f['farmid'];

                        $queue->append($obj);
                    }
                }
            }
        }

        $cnt = count($farms);

        $this->log('INFO', "%d lease task%s %s found", $cnt, ($cnt != 1 ? 's' : ''), ($cnt != 1 ? 'were' : 'was'));

        return $queue;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::worker()
     */
    public function worker($request)
    {
        //Warming up static DI cache
        \Scalr::getContainer()->warmup();

        // Reconfigure observers
        \Scalr::ReconfigureObservers();

        try {
            $dbFarm = DBFarm::LoadByID($request->farmId);

            $curDate = new DateTime();

            $td = new DateTime($dbFarm->GetSetting(DBFarm::SETTING_LEASE_TERMINATE_DATE));

            if ($td > $curDate) {
                // only inform user
                $days = $td->diff($curDate)->days;

                $notifications = json_decode($dbFarm->GetSetting(DBFarm::SETTING_LEASE_NOTIFICATION_SEND), true);

                $governance = new Scalr_Governance($dbFarm->EnvID);

                $settings = $governance->getValue(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE, 'notifications');

                if (is_array($settings)) {
                    foreach ($settings as $n) {
                        if (!$notifications[$n['key']] && $n['period'] >= $days) {
                            $mailer = \Scalr::getContainer()->mailer;
                            $tdHuman = Scalr_Util_DateTime::convertDateTime($td, $dbFarm->GetSetting(DBFarm::SETTING_TIMEZONE), 'M j, Y');

                            if ($n['to'] == 'owner') {
                                $user = new Scalr_Account_User();
                                $user->loadById($dbFarm->createdByUserId);

                                if (\Scalr::config('scalr.auth_mode') == 'ldap') {
                                    $email = $user->getSetting(Scalr_Account_User::SETTING_LDAP_EMAIL);
                                    if (!$email) {
                                        $email = $user->getEmail();
                                    }
                                } else {
                                    $email = $user->getEmail();
                                }

                                $mailer->addTo($email);
                            } else {
                                foreach (explode(',', $n['emails']) as $email) {
                                    $mailer->addTo(trim($email));
                                }
                            }

                            $mailer->sendTemplate(SCALR_TEMPLATES_PATH . '/emails/farm_lease_terminate.eml', array(
                                '{{terminate_date}}' => $tdHuman,
                                '{{farm}}'           => $dbFarm->Name,
                                '{{envName}}'        => $dbFarm->GetEnvironmentObject()->name,
                                '{{envId}}'          => $dbFarm->GetEnvironmentObject()->id
                            ));

                            $notifications[$n['key']] = 1;

                            $dbFarm->SetSetting(DBFarm::SETTING_LEASE_NOTIFICATION_SEND, json_encode($notifications));

                            $this->getLogger()->info(
                                "Notification was sent by key: %s about farm: %s by lease manager",
                                $n['key'], $dbFarm->Name
                            );
                        }
                    }
                }
            } else {
                //Terminates farm
                $event = new FarmTerminatedEvent(0, 1, false, 1);

                \Scalr::FireEvent($request->farmId, $event);

                $this->getLogger()->info("Farm: " . $dbFarm->Name . " was terminated by lease manager");
            }
        } catch (Exception $e) {
            throw $e;
        }

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
            //Report warning to php-warnings.log
            trigger_error(sprintf("Demonized mode is not allowed for '%s' job.", $this->name), E_USER_WARNING);

            //Forces normal mode
            $config->daemon = false;
        }

        return $config;
    }
}