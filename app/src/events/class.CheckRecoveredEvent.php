<?php

class CheckRecoveredEvent extends Event
{
    public $DBServer;
    public $check;


    public function __construct(DBServer $DBServer, $check)
    {
        parent::__construct();
        $this->DBServer = $DBServer;
        $this->check = $check;
    }

    public static function GetScriptingVars()
    {
        return array("check" => "Check ID");
    }
}