<?php

	class Scalr_Cronjob_DbMsrMaintenance extends Scalr_System_Cronjob_MultiProcess_DefaultWorker
    {
    	static function getConfig () {
    		return array(
    			"description" => "Maintenace procedures for MSR databases",
    			"processPool" => array(
					"daemonize" => false,
    				"workerMemoryLimit" => 40000,   		
    				"size" => 12,
    				"startupTimeout" => 10000 // 10 seconds
    			),
    			"waitPrevComplete" => true,
    			"fileName" => __FILE__,
    			"memoryLimit" => 500000
    		);
    	}
    	
        private $logger;
        private $db;
        
        public function __construct() {
        	$this->logger = Logger::getLogger(__CLASS__);
        	
        	$this->timeLogger = Logger::getLogger('time');
        	
        	$this->db = Core::GetDBInstance();
        }
        
        function startForking ($workQueue) {
        	// Reopen DB connection after daemonizing
        	$this->db = Core::GetDBInstance(null, true);
        }
        
        function startChild () {
        	// Reopen DB connection in child
        	$this->db = Core::GetDBInstance(null, true);
        	// Reconfigure observers;
        	Scalr::ReconfigureObservers();
        }        
        
        function enqueueWork ($workQueue) {
            
            $rows = $this->db->GetAll("SELECT id FROM farm_roles WHERE platform != ? AND role_id IN (SELECT role_id FROM role_behaviors WHERE behavior IN (?,?,?,?))", 
            	array(SERVER_PLATFORMS::RDS, ROLE_BEHAVIORS::POSTGRESQL, ROLE_BEHAVIORS::REDIS, ROLE_BEHAVIORS::MYSQL2, ROLE_BEHAVIORS::PERCONA)
            );
            $this->logger->info("Found ".count($rows)." DbMsr farm roles...");            
            
            foreach ($rows as $row) {
            	$workQueue->put($row["id"]);
            }
        }

        private function performDbMsrAction($action, DBFarmRole $dbFarmRole)
        {
        	if ($dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_ENABLED")) && $dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_EVERY")) != 0) {
				if ($dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_IS_RUNNING")) == 1) {	                    
                    // Wait for timeout time * 2 (Example: NIVs problem with big mysql snapshots)
                    // We must wait for running bundle process.
                	$timeout = $dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_EVERY"))*(3600*2);
                	$lastTs = $dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_RUNNING_TS"));
	                if ($lastTs+$timeout < time())
	                	$timeouted = true;
                    	
	                if ($timeouted)
	                	$dbFarmRole->SetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_IS_RUNNING"), 0);
                }
                else {
					/*
					 * Check bundle window
					 */                	                    	
                    $period = $dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_EVERY"));
                	$timeout = $period*3600;
                	$lastActionTime = $dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_LAST_TS"));
                	
                	$performAction = false;
                	if ($period % 24 == 0) {
                		if ($lastActionTime) {
                			$days = $period / 24;
                			$day = (int)date("md", strtotime("+{$days} day", $lastActionTime));
                			
                			if ($day > (int)date("md"))
                				return;
                		}
                		
                		$pbwFrom = (int)($dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_TIMEFRAME_START_HH")).$dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_TIMEFRAME_START_MM")));
	                    $pbwTo = (int)($dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_TIMEFRAME_END_HH")).$dbFarmRole->GetSetting(Scalr_Db_Msr::getConstant("DATA_{$action}_TIMEFRAME_END_MM")));
	                    if ($pbwFrom && $pbwTo) {
	                        $current_time = (int)date("Hi");
	                        if ($pbwFrom <= $current_time && $pbwTo >= $current_time)
								$performAction = true;
	                    }
	                    else
	                    	$performAction = true;
                	}
                	else {
                		//Check timeout
                		if ($lastActionTime+$timeout < time())
                			$performAction = true;
                	}
                	
					if ($performAction)
					{
						$behavior = Scalr_Role_Behavior::loadByName($dbFarmRole->GetRoleObject()->getDbMsrBehavior());
							
						if ($action == 'BUNDLE') {
							$behavior->createDataBundle($dbFarmRole);
						}
						
						if ($action == 'BACKUP') {
							$behavior->createBackup($dbFarmRole);
						}
					}
	            }
			}
        }
        
        function handleWork ($farmRoleId) {
        	
        	try {
	        	$dbFarmRole = DBFarmRole::LoadByID($farmRoleId);
	            $dbFarm = $dbFarmRole->GetFarmObject();    
	            
				//skip terminated farms
				if ($dbFarm->Status != FARM_STATUS::RUNNING)
					return;
        	} catch (Exception $e) {
        		return;
        	}
        	
        	
        	//********* Check Replication status *********/
        	//TODO:
        	
        	//********* Bundle database data ***********/
       		$this->performDbMsrAction('BUNDLE', $dbFarmRole);
       		
       		$backupsNotSupported = in_array($dbFarmRole->Platform, array(
       			SERVER_PLATFORMS::CLOUDSTACK,
       			SERVER_PLATFORMS::IDCF,
       			SERVER_PLATFORMS::UCLOUD
       		));
       		
       		//********* Backup database data ***********/
       		if (!$backupsNotSupported)
        		$this->performDbMsrAction('BACKUP', $dbFarmRole);
        }
    }
