<?php

class CheckFailedEvent extends Event
{

    public $DBServer;
    public $check;
    public $details;


    public function __construct(DBServer $DBServer, $check, $details)
    {
        parent::__construct();
        $this->DBServer = $DBServer;
        $this->check = $check;
        $this->details = $details;
    }

    public static function GetScriptingVars()
    {
        return array("check" => "Check ID", "details" => "Details");
    }
}