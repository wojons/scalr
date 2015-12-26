<?php

class HostInitFailedEvent extends AbstractServerEvent
{

    /**
     *
     * @var DBServer
     */
    public $DBServer;
    public $initFailureReason;

    public function __construct(DBServer $dbServer, $initFailureReason)
    {
        parent::__construct();
        $this->DBServer = $dbServer;
        $this->initFailureReason = $initFailureReason;
    }

    public static function GetScriptingVars()
    {
        return array(
            "initialization_failure_reason" => "initFailureReason"
        );
    }
}
