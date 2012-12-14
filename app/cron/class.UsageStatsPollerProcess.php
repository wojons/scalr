<?
	class UsageStatsPollerProcess implements IProcess
    {
        public $ThreadArgs;
        public $ProcessDescription = "Farm usage stats poller";
        public $Logger;
        
    	public function __construct()
        {
        	// Get Logger instance
        	$this->Logger = Logger::getLogger(__CLASS__);
        }
        
        public function OnStartForking()
        {
            $db = Core::GetDBInstance();
            
            $this->Logger->info("Fetching running farms...");
            
            $this->ThreadArgs = $db->GetAll("SELECT farms.id as id FROM farms 
            	INNER JOIN clients ON clients.id = farms.clientid WHERE clients.status='Active' AND farms.status=?",
            	array(FARM_STATUS::RUNNING)
            );
                        
            $this->Logger->info("Found ".count($this->ThreadArgs)." farms.");
        }
        
        public function OnEndForking()
        {

        }
        
        public function StartThread($farminfo)
        {
            $db = Core::GetDBInstance();
            $snmpClient = new Scalr_Net_Snmp_Client();
            
            $DBFarm = DBFarm::LoadByID($farminfo['id']);

            foreach ($DBFarm->GetFarmRoles() as $DBFarmRole)
            {
                foreach ($DBFarmRole->GetServersByFilter(array(), array('status' => array(SERVER_STATUS::TERMINATED, SERVER_STATUS::PENDING_LAUNCH, SERVER_STATUS::TEMPORARY, SERVER_STATUS::IMPORTING))) as $DBServer)
                {                	
                    $launchTime = strtotime($DBServer->dateAdded);
                    $lastCheckTime = (int)$DBServer->GetProperty(SERVER_PROPERTIES::STATISTICS_LAST_CHECK_TS);
                    if (!$lastCheckTime)
                    	$lastCheckTime = $launchTime;
                    
                    $period = round((time()-$lastCheckTime) / 60);
                    
                    $maxMinutes = (date("j")*24*60) - (date("H")*60);
                    if ($period > $maxMinutes)
                    	$period = $maxMinutes;
                    
                    $serverType = $DBServer->GetFlavor();
                    
                    if (!$serverType)
                    	continue;
                    
                    $db->Execute("INSERT INTO servers_stats SET
                    	`usage` = ?,
                    	`instance_type` = ?,
                    	`env_id` = ?,
                    	`month` = ?,
                    	`year` = ?,
                    	`farm_id` = ?,
                    	`cloud_location` = ?
                    ON DUPLICATE KEY UPDATE `usage` = `usage` + ?             
                    ", array(
                    	$period,
                    	$serverType,
                    	$DBServer->envId,
                    	date("m"),
                    	date("Y"),
                    	$DBServer->farmId,
                    	$DBServer->GetCloudLocation(),
                    	$period
                    ));
                    
                    $DBServer->SetProperty(SERVER_PROPERTIES::STATISTICS_LAST_CHECK_TS, time());
                } //for each items
            }
        }
    }
?>