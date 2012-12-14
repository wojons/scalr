<?

	class ScalrEnvironment20120701 extends ScalrEnvironment20120417
    {    	
    	public function GetGlobalConfig()
    	{
    		$ResponseDOMDocument = $this->CreateResponse();
    		$configNode = $ResponseDOMDocument->createElement("settings");
    		
    		// Get DNS zconfig
    		$cfg = @parse_ini_file(APPPATH.'/etc/dns.ini', true);
    		
    		$config = array(
    			'dns.static.endpoint' => $cfg['static']['domain_name'],
    			'scalr.version'	=> SCALR_VERSION,
    			'scalr.id'		=> SCALR_ID
    		);
    		
    		foreach ($config as $key => $value)
    		{
    			$settingNode = $ResponseDOMDocument->createElement("setting", $value);
				$settingNode->setAttribute("key", $key);
				$configNode->appendChild($settingNode);
    		}
    		
    		
    		$ResponseDOMDocument->documentElement->appendChild($configNode);
    		return $ResponseDOMDocument;
    	}
    	
    	public function ListFarmRoleParams()
    	{
    		$farmRoleId = $this->GetArg("farm-role-id");
    		if (!$farmRoleId)
    			throw new Exception("'farm-role-id' required");
    		
    		$dbFarmRole = DBFarmRole::LoadByID($farmRoleId);
    		if ($dbFarmRole->FarmID != $this->DBServer->farmId)
    			throw new Exception("You can request this information ONLY for roles within server farm");
    	
    		$ResponseDOMDocument = $this->CreateResponse();
    	
    		$role = $dbFarmRole->GetRoleObject();
    		$behaviors = $role->getBehaviors();
    		foreach ($behaviors as $behavior) {
    			if ($behavior == ROLE_BEHAVIORS::CF_CLOUD_CONTROLLER) {
    				$data = new stdClass();
    				$data->version = $dbFarmRole->GetSetting(Scalr_Role_Behavior_CfCloudController::ROLE_VERSION);

    				$bodyEl = $this->serialize($data, $behavior, $ResponseDOMDocument);
    				
    				$ResponseDOMDocument->documentElement->appendChild($bodyEl);
    			}
    			else if ($behavior == ROLE_BEHAVIORS::MYSQL) {
    				$data = new stdClass();
    				$data->logFile = $dbFarmRole->GetSetting(DBFarmRole::SETTING_MYSQL_LOG_FILE);
    				$data->logPos = $dbFarmRole->GetSetting(DBFarmRole::SETTING_MYSQL_LOG_POS);
    				$data->rootPassword = $dbFarmRole->GetSetting(DBFarmRole::SETTING_MYSQL_ROOT_PASSWORD);
    				$data->replPassword = $dbFarmRole->GetSetting(DBFarmRole::SETTING_MYSQL_REPL_PASSWORD);
    				$data->statPassword = $dbFarmRole->GetSetting(DBFarmRole::SETTING_MYSQL_STAT_PASSWORD);
    				$data->replicationMaster = (int)$this->DBServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER);
    				//TODO: Storage
    				
    				$bodyEl = $this->serialize($data, $behavior, $ResponseDOMDocument);
    				
    				$ResponseDOMDocument->documentElement->appendChild($bodyEl);
    				
    			} else {
    				
    				try {
	    				$dbMsrInfo = Scalr_Db_Msr_Info::init($dbFarmRole, $this->DBServer, $behavior);
	    				$obj = $dbMsrInfo->getMessageProperties();
	    				
	    				$bodyEl = $this->serialize($obj, $behavior, $ResponseDOMDocument);
	    				
	    				$ResponseDOMDocument->documentElement->appendChild($bodyEl);
    				} catch (Exception $e) {}
    			}
    		}
    		
    		return $ResponseDOMDocument;
    	}
    	
    	private function serialize ($object, $behavior, $doc) {
    	
    		$bodyEl = $doc->createElement($behavior);
    		$body = array();
    		foreach (get_object_vars($object) as $k => $v) {
    			$body[$k] = $v;
    		}

    		$this->walkSerialize($body, $bodyEl, $doc);
    	
    		return $bodyEl;
    	}
    	
    	private function walkSerialize ($value, $el, $doc) {
			if (is_array($value) || is_object($value)) {
				if (is_array($value) && array_keys($value) === range(0, count($value)-1)) {
					// Numeric indexes array
					foreach ($value as $v) {
						$itemEl = $doc->createElement("item");
						$el->appendChild($itemEl);
						$this->walkSerialize($v, $itemEl, $doc);
					}
				} else {
					// Assoc arrays and objects
					foreach ($value as $k => $v) {
						$itemEl = $doc->createElement($this->under_scope($k));
						$el->appendChild($itemEl);
						$this->walkSerialize($v, $itemEl, $doc);
					}
				}
			} else {
				if (preg_match("/[\<\>\&]+/", $value)) {
					$valueEl = $doc->createCDATASection($value);
				} else {
					$valueEl = $doc->createTextNode($value);
				}
				$el->appendChild($valueEl);
			}
		}
		
		private function under_scope ($name) {
			$parts = preg_split("/[A-Z]/", $name, -1, PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_NO_EMPTY);
			$ret = "";
			foreach ($parts as $part) {
				if ($part[1]) {
					$ret .= "_" . strtolower($name{$part[1]-1});
				}
				$ret .= $part[0];
			}
			return $ret;
		}
    }
?>