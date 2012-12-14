<?php

class Scalr_Db_Msr_Percona_Info extends Scalr_Db_Msr_Mysql2_Info
{
	
	public function __construct(DBFarmRole $dbFarmRole, DBServer $dbServer) {
		
		parent::__construct($dbFarmRole, $dbServer, ROLE_BEHAVIORS::PERCONA);

		$this->rootPassword = $dbFarmRole->GetSetting(Scalr_Db_Msr_Mysql2::ROOT_PASSWORD);
		$this->replPassword = $dbFarmRole->GetSetting(Scalr_Db_Msr_Mysql2::REPL_PASSWORD);
		$this->statPassword = $dbFarmRole->GetSetting(Scalr_Db_Msr_Mysql2::STAT_PASSWORD);
		
		$this->logPos = $dbFarmRole->GetSetting(Scalr_Db_Msr_Mysql2::LOG_POS);
		$this->logFile = $dbFarmRole->GetSetting(Scalr_Db_Msr_Mysql2::LOG_FILE);
	}
}