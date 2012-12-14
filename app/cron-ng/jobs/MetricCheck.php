<?php

	use \Scalr\Server\Alerts;

	class Scalr_Cronjob_MetricCheck extends Scalr_System_Cronjob_MultiProcess_DefaultWorker
    {
    	static function getConfig () {
    		return array(
    			"description" => "Metrics check",
    			"processPool" => array(
					"daemonize" => false,
    				"workerMemoryLimit" => 40000,   		
    				"size" => 20,
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
            
            $rows = $this->db->GetAll("SELECT id FROM farms WHERE status=? AND clientid IN (SELECT id FROM clients WHERE status = 'Active')",
            	array(FARM_STATUS::RUNNING)
            );           
            
            foreach ($rows as $row) {
            	$workQueue->put($row["id"]);
            }
        }

        function handleWork ($farmId) {
        	
			try {
				$dbFarm = DBFarm::LoadByID($farmId);
				if ($dbFarm->Status != FARM_STATUS::RUNNING)
					return;
			} catch (Exception $e) {
				return;
			}
			
			foreach ($dbFarm->GetFarmRoles() as $dbFarmRole)
			{
				$instancesHealth = array();
				if ($dbFarmRole->Platform == SERVER_PLATFORMS::EC2) {
					
					$env = Scalr_Environment::init()->loadById($dbFarm->EnvID);
					
					$ec2Client = Scalr_Service_Cloud_Aws::newEc2(
						$dbFarmRole->CloudLocation, 
						$env->getPlatformConfigValue(Modules_Platforms_Ec2::PRIVATE_KEY),
						$env->getPlatformConfigValue(Modules_Platforms_Ec2::CERTIFICATE)
					);
					
					$status = $ec2Client->DescribeInstanceStatus();
					if (!is_array($status->instanceStatusSet->item))
						$status->instanceStatusSet->item = array($status->instanceStatusSet->item);
					
					foreach ($status->instanceStatusSet->item as $item) {
						$instancesHealth[$item->instanceId] = $item;
					}
					unset($status);
				}
				
				//TODO:
				$servers = $this->db->Execute("SELECT server_id FROM servers WHERE farm_roleid = ? AND status = ?", array($dbFarmRole->ID, SERVER_STATUS::RUNNING));
				while ($server = $servers->FetchRow()) {
		        	$dbServer = DBServer::LoadByID($server['server_id']);
					
					// Do not support ami-scripts
					if (!$dbServer->IsSupported("0.5"))
						continue;
					
					// Do not support windows
					if ($dbServer->IsSupported("0.8") && !$dbServer->IsSupported("0.9"))
						continue;
					
					if ($dbServer->GetProperty(SERVER_PROPERTIES::SUB_STATUS) != '')
						continue;
					
					if ($dbServer->GetProperty(SERVER_PROPERTIES::REBOOTING))
						continue;
					
		            $serverAlerts = new Alerts($dbServer);
					
					//Check AWS healthchecks
					if ($dbServer->platform == SERVER_PLATFORMS::EC2) {
						try {
							$statusInfo = $instancesHealth[$dbServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID)];
							if ($statusInfo) {
								$metric = Alerts::METRIC_AWS_SYSTEM_STATUS;
								$hasActiveAlert = $serverAlerts->hasActiveAlert($metric);
								if ($statusInfo->systemStatus->status == 'ok' && $hasActiveAlert) {
									Scalr::FireEvent($dbServer->farmId, 
										new MetricCheckRecoveredEvent($dbServer, $metric)
									);
								}
								elseif ($statusInfo->systemStatus->status != 'ok' && !$hasActiveAlert) {
									$txtDetails = "";
									$details = $statusInfo->systemStatus->details->item;
									if ($details->name)
										$details = array($details);
									
									foreach ($details as $d)
										$txtDetails .= " {$d->name} is {$d->status},";
									$txtDetails = trim($txtDetails, " ,");
									
									Scalr::FireEvent($dbServer->farmId, 
										new MetricCheckFailedEvent($dbServer, $metric, "{$statusInfo->systemStatus->status}: {$txtDetails}")
									);
								}
								
								$metric = Alerts::METRIC_AWS_INSTANCE_STATUS;
								$hasActiveAlert = $serverAlerts->hasActiveAlert($metric);
								if ($statusInfo->instanceStatus->status == 'ok' && $hasActiveAlert)
									Scalr::FireEvent($dbServer->farmId, 
										new MetricCheckRecoveredEvent($dbServer, $metric)
									);
								else if ($statusInfo->instanceStatus->status != 'ok' && !$hasActiveAlert) {
									$txtDetails = "";
									$details = $statusInfo->instanceStatus->details->item;
									if ($details->name)
										$details = array($details);
									
									foreach ($details as $d)
										$txtDetails .= " {$d->name} is {$d->status},";
									$txtDetails = trim($txtDetails, " ,");
									
									Scalr::FireEvent($dbServer->farmId, 
										new MetricCheckFailedEvent($dbServer, $metric, "{$statusInfo->instanceStatus->status}: {$txtDetails}")
									);
								}
							}
						} catch (Exception $e) {}
					}
					
		   			//Check scalr-upd-client status
		   			$metric = Alerts::METRIC_SCALARIZR_UPD_CLIENT_CONNECTIVITY;
		   			$port = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_UPDC_PORT);
					if (!$port) $port = 8008;
		   			$result = $this->checkPort($dbServer->remoteIp, $port);
					$hasActiveAlert = $serverAlerts->hasActiveAlert($metric);
					if (!$result['status'] && !$hasActiveAlert) {
						Scalr::FireEvent($dbServer->farmId, 
							new MetricCheckFailedEvent($dbServer, $metric, $result['error'])
						);
					} elseif ($result['status'] && $hasActiveAlert) {
						Scalr::FireEvent($dbServer->farmId, 
							new MetricCheckRecoveredEvent($dbServer, $metric)
						);
					} elseif($hasActiveAlert) {
						$serverAlerts->updateLastCheckTime($metric);
					}
					
					//Check scalarizr connectivity status
					$metric = Alerts::METRIC_SCALARIZR_CONNECTIVITY;
					$port = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_CTRL_PORT);
					if (!$port) $port = 8013;
					$result = $this->checkPort($dbServer->remoteIp, $port);
					$hasActiveAlert = $serverAlerts->hasActiveAlert($metric);
					if (!$result['status'] && !$hasActiveAlert) {
						Scalr::FireEvent($dbServer->farmId, 
							new MetricCheckFailedEvent($dbServer, $metric, $result['error'])
						);
					} elseif ($result['status'] && $hasActiveAlert) {
						Scalr::FireEvent($dbServer->farmId, 
							new MetricCheckRecoveredEvent($dbServer, $metric)
						);
					} elseif($hasActiveAlert) {
						$serverAlerts->updateLastCheckTime($metric);
					}
				}
			}
			exit();
        }
		
		private function checkPort($host, $port)
		{
			try {
   				$chk = @fsockopen($host, $port, $errno, $errstr, 5);
				if (!$chk) {
					return array('status' => false, 'error' => "{$errstr} ({$errno})");
				} else {
					@fclose($chk);
					return array('status' => true);
				}
   			} catch (Exception $e) {
   				return array('status' => false, 'error' => $e->getMessage());
   			}
		}
    }
