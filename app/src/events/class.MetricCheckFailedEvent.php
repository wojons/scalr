<?php
	
	class MetricCheckFailedEvent extends Event 
	{
	
		public $dBServer;
		public $metric;
		public $details;
		
		
		public function __construct(DBServer $dBServer, $metric, $details)
		{
			parent::__construct();
			$this->dBServer = $dBServer;
			$this->metric = $metric;
			$this->details = $details;
		}
		
		public static function GetScriptingVars()
		{
			return array("metric" => "Metric ID", "details" => "Details");
		}
	}