<?php
	
	class MetricCheckRecoveredEvent extends Event 
	{
		public $dBServer;
		public $metric;
		
		
		public function __construct(DBServer $dBServer, $metric)
		{
			parent::__construct();
			$this->dBServer = $dBServer;
			$this->metric = $metric;
		}
		
		public static function GetScriptingVars()
		{
			return array("metric" => "Metric ID");
		}
	}