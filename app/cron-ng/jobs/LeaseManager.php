<?php

class Scalr_Cronjob_LeaseManager extends Scalr_System_Cronjob_MultiProcess_DefaultWorker
{
    static function getConfig () {
        return array(
            "description" => "Lease manager",
            "processPool" => array(
                "daemonize" => false,
                "workerMemoryLimit" => 40000,
                "size" => 3,
                "startupTimeout" => 10000 // 10 seconds
            ),
            "waitPrevComplete" => true,
            "fileName" => __FILE__,
            "memoryLimit" => 500000
        );
    }

    private $logger;

    /**
     * @var \ADODB_mysqli
     */
    private $db;

    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);

        $this->timeLogger = Logger::getLogger('time');

        $this->db = $this->getContainer()->adodb;
    }

    function startForking ($workQueue)
    {
        // Reopen DB connection after daemonizing
        $this->db = $this->getContainer()->adodb;
    }

    function startChild ()
    {
        // Reopen DB connection in child
        $this->db = $this->getContainer()->adodb;
        // Reconfigure observers;
        Scalr::ReconfigureObservers();
    }

    function enqueueWork ($workQueue) {
        $this->logger->info("Fetching farms...");

        $farms = array();
        $envs = $this->db->GetAll('SELECT env_id, value FROM governance WHERE enabled = 1 AND name = ?', array(Scalr_Governance::GENERAL_LEASE));
        foreach ($envs as $env) {
            $env['value'] = json_decode($env['value'], true);
            $period = 0;
            if (is_array($env['value']['notifications'])) {
                foreach ($env['value']['notifications'] as $notif) {
                    if ($notif['period'] > $period)
                        $period = $notif['period'];
                }

                $dt = new DateTime();
                $dt->sub(new DateInterval('P' . $period . 'D'));

                $fs = $this->db->GetAll('SELECT farmid, status FROM farm_settings
                LEFT JOIN farms ON farms.id = farm_settings.farmid
                WHERE farm_settings.name = ? AND status = ? AND env_id = ? AND value > ?',
                    array(
                        DBFarm::SETTING_LEASE_TERMINATE_DATE,
                        FARM_STATUS::RUNNING,
                        $env['env_id'],
                        $dt->format('Y-m-d H:i:s')
                    ));

                foreach ($fs as $f) {
                    if (!isset($farms[$f['farmid']])) {
                        $farms[$f['farmid']] = true;
                        $workQueue->put($f['farmid']);
                    }
                }
            }
        }

        $this->logger->info("Found " . count($farms) . " lease tasks");
    }

    function handleWork ($farmId)
    {
        try {
            $dbFarm = DBFarm::LoadByID($farmId);
            $governance = new Scalr_Governance($dbFarm->EnvID);
            $settings = $governance->getValue(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE, 'notifications');
            $curDate = new DateTime();
            $td = new DateTime($dbFarm->GetSetting(DBFarm::SETTING_LEASE_TERMINATE_DATE));
            if ($td > $curDate) {
                // only inform user
                $days = $td->diff($curDate)->days;
                $notifications = json_decode($dbFarm->GetSetting(DBFarm::SETTING_LEASE_NOTIFICATION_SEND), true);

                if (is_array($settings)) {
                    foreach ($settings as $n) {
                        if (!$notifications[$n['key']] && $n['period'] >= $days) {
                            $mailer = Scalr::getContainer()->mailer;
                            $tdHuman = Scalr_Util_DateTime::convertDateTime($td, $dbFarm->GetSetting(DBFarm::SETTING_TIMEZONE), 'M j, Y');

                            if ($n['to'] == 'owner') {
                                $user = new Scalr_Account_User();
                                $user->loadById($dbFarm->createdByUserId);

                                if (Scalr::config('scalr.auth_mode') == 'ldap') {
                                    $email = $user->getSetting(Scalr_Account_User::SETTING_LDAP_EMAIL);
                                    if (! $email)
                                        $email = $user->getEmail();
                                } else {
                                    $email = $user->getEmail();
                                }

                                $mailer->addTo($email);
                            } else {
                                foreach(explode(',', $n['emails']) as $email)
                                    $mailer->addTo(trim($email));
                            }

                            $mailer->sendTemplate(
                                SCALR_TEMPLATES_PATH . '/emails/farm_lease_terminate.eml',
                                array(
                                    '{{terminate_date}}' => $tdHuman,
                                    '{{farm}}' => $dbFarm->Name,
                                    '{{envName}}' => $dbFarm->GetEnvironmentObject()->name,
                                    '{{envId}}' => $dbFarm->GetEnvironmentObject()->id
                                )
                            );

                            $notifications[$n['key']] = 1;
                            $dbFarm->SetSetting(DBFarm::SETTING_LEASE_NOTIFICATION_SEND, json_encode($notifications));
                            $this->logger->info("Notification was sent by key: " . $n['key'] . " about farm: " . $dbFarm->Name . " by lease manager");
                        }
                    }
                }
            } else {
                // terminate farm
                $event = new FarmTerminatedEvent(0, 1, false, 1);
                Scalr::FireEvent($farmId, $event);
                $this->logger->info("Farm: " . $dbFarm->Name . " was terminated by lease manager");
            }
        }
        catch(Exception $e) {
            var_dump($e->getMessage());
        }
    }
}
