<?php 
	class Scalr_Role_DbMsrBehavior extends Scalr_Role_Behavior
	{		
		/* ALL SETTINGS IN SCALR_DB_MSR_* */
		const ROLE_DATA_STORAGE_LVM_VOLUMES = 'db.msr.storage.lvm.volumes';
		const ROLE_DATA_BUNDLE_USE_SLAVE	= 'db.msr.data_bundle.use_slave';
		
		protected $behavior;
		
		public function __construct($behaviorName)
		{
			parent::__construct($behaviorName);
		}
		
		public function createBackup(DBFarmRole $dbFarmRole)
		{
			if ($dbFarmRole->GetSetting(Scalr_Db_Msr::DATA_BACKUP_IS_RUNNING) == 1)
				throw new Exception("Backup already in progress");
				
			$currentServer = $this->getServerForBackup($dbFarmRole);
				
			if (!$currentServer)
				throw new Exception("No suitable server for backup");
							
			$currentServer->SendMessage(new Scalr_Messaging_Msg_DbMsr_CreateBackup());
			$dbFarmRole->SetSetting(Scalr_Db_Msr::getConstant("DATA_BACKUP_IS_RUNNING"), 1);
			$dbFarmRole->SetSetting(Scalr_Db_Msr::getConstant("DATA_BACKUP_RUNNING_TS"), time());
			$dbFarmRole->SetSetting(Scalr_Db_Msr::getConstant("DATA_BACKUP_SERVER_ID"), $currentServer->serverId);
		}
		
		public function createDataBundle(DBFarmRole $dbFarmRole) {
			
			if ($dbFarmRole->GetSetting(Scalr_Db_Msr::DATA_BUNDLE_IS_RUNNING) == 1)
				throw new Exception("Data bundle already in progress");
			
			$currentServer = $this->getServerForDataBundle($dbFarmRole);
			if (!$currentServer)
				throw new Exception("No suitable server for data bundle");
							
			$message = new Scalr_Messaging_Msg_DbMsr_CreateDataBundle();
				
			$storageType = $dbFarmRole->GetSetting(Scalr_Db_Msr::DATA_STORAGE_ENGINE);
			$storageGeneration = $storageType == 'lvm' ? 2 : 1;
			if ($storageGeneration == 2) {
				$behavior = $dbFarmRole->GetRoleObject()->getDbMsrBehavior();
				$backupConfig = $this->db->GetRow("SELECT * FROM storage_backup_configs WHERE farm_roleid = ? ORDER BY id DESC", array($dbFarmRole->ID));
				
				$message->{$behavior}->backup->type = 'xtrabackup';
				$message->{$behavior}->backup->backupType = 'full';
				
				if (!$backupConfig) {
					$message->{$behavior}->backup->volume = new stdClass();
					$message->{$behavior}->backup->volume->type = 'lvm';
					$message->{$behavior}->backup->volume->pvs = array(
						array('type' => 'ebs', 'size' => 1000),
						array('type' => 'ebs', 'size' => 1000)
					);
					$message->{$behavior}->backup->volume->vg = 'xtrabackup';
					$message->{$behavior}->backup->volume->name = 'data';
					$message->{$behavior}->backup->volume->size = '100%VG';
					$message->{$behavior}->backup->volume->fstype = 'xfs';
				} else {
					$message->{$behavior}->backup->volume = @json_decode($backupConfig['volume_config']);
				}
			}
			
			$message->storageType = $storageType;
			
			$currentServer->SendMessage($message);
			
			$dbFarmRole->SetSetting(Scalr_Db_Msr::getConstant("DATA_BUNDLE_IS_RUNNING"), 1);
			$dbFarmRole->SetSetting(Scalr_Db_Msr::getConstant("DATA_BUNDLE_RUNNING_TS"), time());
			$dbFarmRole->SetSetting(Scalr_Db_Msr::getConstant("DATA_BUNDLE_SERVER_ID"), $currentServer->serverId);
		}
		
		public function getServerForDataBundle(DBFarmRole $dbFarmRole)
		{
			$useSlave = $dbFarmRole->GetSetting(Scalr_Role_DbMsrBehavior::ROLE_DATA_BUNDLE_USE_SLAVE);
							
			// perform data bundle on master
       		$servers = $dbFarmRole->GetServersByFilter(array('status' => array(SERVER_STATUS::RUNNING)));
			$currentServer = null;
			$currentMetric = 0;
			foreach ($servers as $dbServer) {
				
				$isMaster = $dbServer->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER);
				
				if ($isMaster) {
					$masterServer = $dbServer;
					if (!$useSlave) {
						$currentServer = $dbServer;
						break;
					}
				}
				
				if (!$isMaster) {
					$currentServer = $dbServer;
					break;	
				}
				
				/*
				$metric = $this->getBinLogPosition($dbFarmRole, $dbServer);
				if ($metric > $currentMetric) {
					$currentServer = $dbServer;
					$currentMetric = $metric;
				}
				 */
			}
			
			return $currentServer;
		}
		
		public function getServerForBackup(DBFarmRole $dbFarmRole)
		{
			$servers = $dbFarmRole->GetServersByFilter(array('status' => array(SERVER_STATUS::RUNNING)));
			$currentServer = null;
			$currentMetric = 0;
			foreach ($servers as $dbServer) {
				
				$isMaster = $dbServer->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER);
				
				if ($isMaster) {
					$masterServer = $dbServer;
					if (!$currentServer) {
						$currentServer = $dbServer;
					}
				}
				
				if (!$isMaster) {
					$currentServer = $dbServer;
					break;
				}
			}
			
			return $currentServer;
		}
		
		public function extendMessage(Scalr_Messaging_Msg $message, DBServer $dbServer)
		{
			$message = parent::extendMessage($message);
			
			try {
				$dbFarmRole = $dbServer->GetFarmRoleObject();
				$storageType = $dbFarmRole->GetSetting(Scalr_Db_Msr::DATA_STORAGE_ENGINE);
				$storageGeneration = $storageType == 'lvm' ? 2 : 1;
			} catch (Exception $e) {}
			
			switch (get_class($message))
			{
				case "Scalr_Messaging_Msg_HostInitResponse":
					
					$dbMsrInfo = Scalr_Db_Msr_Info::init($dbFarmRole, $dbServer, $this->behavior);
					$message->addDbMsrInfo($dbMsrInfo);
					
					if ($storageGeneration == 2) {
						
						$message->{$this->behavior}->volumeConfig = null;
						$message->{$this->behavior}->snapshotConfig = null;
						
						// Create volume configuration
						$message->{$this->behavior}->volume = new stdClass();
						$message->{$this->behavior}->volume->type = 'lvm';
						
						$volumes = $dbFarmRole->GetSetting(self::ROLE_DATA_STORAGE_LVM_VOLUMES);
						if (!$volumes) {
							$message->{$this->behavior}->volume->pvs = array(
								array('type' => 'ec2_ephemeral', 'name' => 'ephemeral0'),
								array('type' => 'ec2_ephemeral', 'name' => 'ephemeral1')
							);	
						} else {
							$v = json_decode($volumes);
							$message->{$this->behavior}->volume->pvs = array();
							foreach ($v as $name => $size) {
								$message->{$this->behavior}->volume->pvs[] = array('type' => 'ec2_ephemeral', 'name' => $name);
							}
						}
					
						$message->{$this->behavior}->volume->vg = $this->behavior;
				      	$message->{$this->behavior}->volume->name = 'data';
				      	$message->{$this->behavior}->volume->size = '100%VG';
				      	$message->{$this->behavior}->volume->fstype = 'xfs';
						
						// Add restore configuration
						$restore = $this->db->GetRow("SELECT * FROM storage_restore_configs WHERE farm_roleid = ? ORDER BY id DESC", array($dbFarmRole->ID));
						if ($restore)
							$message->{$this->behavior}->restore = @json_decode($restore['restore_config']);
							
						// Add backup configuration
						if (!$message->{$this->behavior}->restore) {
							$message->{$this->behavior}->backup = new stdClass();
						 	$message->{$this->behavior}->backup->type = 'xtrabackup';
							$message->{$this->behavior}->backup->backupType = 'full';
							$message->{$this->behavior}->backup->volume = new stdClass();
							$message->{$this->behavior}->backup->volume->type = 'lvm';
							$message->{$this->behavior}->backup->volume->pvs = array(
								array('type' => 'ebs', 'size' => 1000),
								array('type' => 'ebs', 'size' => 1000)
							);
							$message->{$this->behavior}->backup->volume->vg = 'xtrabackup';
							$message->{$this->behavior}->backup->volume->name = 'data';
							$message->{$this->behavior}->backup->volume->size = '100%VG';
							$message->{$this->behavior}->backup->volume->fstype = 'xfs';
						}
					}
					
					break;
					
				case "Scalr_Messaging_Msg_DbMsr_PromoteToMaster":
							
					$dbMsrInfo = Scalr_Db_Msr_Info::init($dbFarmRole, $dbServer, $this->behavior);
					$message->addDbMsrInfo($dbMsrInfo);
							
					if ($storageGeneration == 2) {
						
						$message->{$this->behavior}->volumeConfig = null;
						$message->{$this->behavior}->snapshotConfig = null;
						
						$message->{$this->behavior}->backup = new stdClass();
					 	$message->{$this->behavior}->backup->type = 'xtrabackup';
						$message->{$this->behavior}->backup->backupType = 'full';
						
						$backupConfig = $this->db->GetRow("SELECT * FROM storage_backup_configs WHERE farm_roleid = ? ORDER BY id DESC", array($dbFarmRole->ID));
						if (!$backupConfig) {
							$message->{$this->behavior}->backup->volume = new stdClass();
							$message->{$this->behavior}->backup->volume->type = 'lvm';
							$message->{$this->behavior}->backup->volume->pvs = array(
								array('type' => 'ebs', 'size' => 1),
								array('type' => 'ebs', 'size' => 1)
							);
							$message->{$this->behavior}->backup->volume->vg = 'xtrabackup';
							$message->{$this->behavior}->backup->volume->name = 'data';
							$message->{$this->behavior}->backup->volume->size = '100%VG';
							$message->{$this->behavior}->backup->volume->fstype = 'xfs';
						} else {
							$message->{$this->behavior}->backup->volume = @json_decode($backupConfig['volume_config']);
						}
					}
							
					break;
					
				case "Scalr_Messaging_Msg_DbMsr_NewMasterUp":
					
					$dbMsrInfo = Scalr_Db_Msr_Info::init($dbFarmRole, $dbServer, $this->behavior);
					$message->addDbMsrInfo($dbMsrInfo);
					
					if ($storageGeneration == 2) {
						
						$message->{$this->behavior}->volumeConfig = null;
						$message->{$this->behavior}->snapshotConfig = null;
						
						$restore = $this->db->GetRow("SELECT * FROM storage_restore_configs WHERE farm_roleid = ? ORDER BY id DESC", array($dbFarmRole->ID));
						if ($restore)
							$message->{$this->behavior}->restore = @json_decode($restore['restore_config']);
					}
					
					break;
			}
			
			return $message;
		}
		
		public function handleMessage(Scalr_Messaging_Msg $message, DBServer $dbServer) 
		{ 
			try {
				$dbFarmRole = $dbServer->GetFarmRoleObject();
				$storageType = $dbFarmRole->GetSetting(Scalr_Db_Msr::DATA_STORAGE_ENGINE);
				$storageGeneration = $storageType == 'lvm' ? 2 : 1;
			} catch (Exception $e) {}
				
			switch (get_class($message))
			{
				case "Scalr_Messaging_Msg_HostUp":
					
					if ($message->dbType && in_array($message->dbType, array(ROLE_BEHAVIORS::REDIS, ROLE_BEHAVIORS::POSTGRESQL, ROLE_BEHAVIORS::MYSQL2, ROLE_BEHAVIORS::PERCONA)))
					{
						$dbMsrInfo = Scalr_Db_Msr_Info::init($dbFarmRole, $dbServer, $message->dbType);
       					$dbMsrInfo->setMsrSettings($message->{$message->dbType});	
						
						if ($message->{$message->dbType}->restore) {
							$this->db->GetRow("INSERT INTO storage_restore_configs SET farm_roleid = ?, dtadded=NOW(), restore_config = ?", array(
								$dbFarmRole->ID,
								@json_encode($message->{$message->dbType}->restore)
							));
						}
						
						if ($message->{$message->dbType}->backup) {
							$this->db->GetRow("INSERT INTO storage_backup_configs SET farm_roleid = ?, type=?, volume_config = ?, backup_type = ?", array(
								$dbFarmRole->ID,
								$message->{$message->dbType}->backup->type,
								@json_encode($message->{$message->dbType}->backup->volume),
								$message->{$message->dbType}->backup->backupType
							));
						}
					}
					
					break;
				
				case "Scalr_Messaging_Msg_DbMsr_PromoteToMasterResult":
					
					if ($message->{$message->dbType}->restore) {
						$this->db->GetRow("INSERT INTO storage_restore_configs SET farm_roleid = ?, dtadded=NOW(), restore_config = ?", array(
							$dbFarmRole->ID,
							@json_encode($message->{$message->dbType}->restore)
						));
					}
					
					if (Scalr_Db_Msr::onPromoteToMasterResult($message, $dbServer)) 
	       				Scalr::FireEvent($dbServer->farmId, new NewDbMsrMasterUpEvent($dbServer));
	       				
					break;
					
				case "Scalr_Messaging_Msg_DbMsr_CreateDataBundleResult":

					if ($message->status == "ok") {
						if ($message->{$message->dbType}->restore) {
							$this->db->GetRow("INSERT INTO storage_restore_configs SET farm_roleid = ?, dtadded=NOW(), restore_config = ?", array(
								$dbFarmRole->ID,
								@json_encode($message->{$message->dbType}->restore)
							));
						}
						
       					Scalr_Db_Msr::onCreateDataBundleResult($message, $dbServer);
       				}
       				else {
       					$dbFarmRole->SetSetting(Scalr_Db_Msr::DATA_BUNDLE_IS_RUNNING, 0);
       						//TODO: store last error
       				}
					
					break;
					
				case "Scalr_Messaging_Msg_DbMsr_CreateBackupResult":

					if ($message->status == "ok")
       					Scalr_Db_Msr::onCreateBackupResult($message, $dbServer);
       				else {
       					$dbFarmRole->SetSetting(Scalr_Db_Msr::DATA_BACKUP_IS_RUNNING, 0);
       						//TODO: store last error
       				}
					
					break;
			}
		}
	}
