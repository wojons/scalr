<?php

class Scalr_Messaging_Msg_DbMsr_CreateBackup extends Scalr_Messaging_Msg_DbMsr {
	
	public $scripts = array();
	
	//public $rootPassword;
	
	function __construct () {
		parent::__construct();		
		//$this->rootPassword = $rootPassword;
	}
}