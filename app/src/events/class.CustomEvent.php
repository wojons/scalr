<?php
	
	class CustomEvent extends Event 
	{
	
		public $DBServer;
		private $eventName;
		public $params;
		
		public function __construct(DBServer $DBServer, $eventName, $params)
		{
			parent::__construct();
			$this->eventName = $eventName;
			$this->params = $params;
			$this->DBServer = $DBServer;
		}
	
		/**
		 * Returns event name
		 *
		 * @return string
		 */
		public function GetName()
		{
			return $this->eventName;
		}
	}
?>