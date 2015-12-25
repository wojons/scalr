<?php

class InstanceLaunchFailedEvent extends AbstractServerEvent
{

    /**
     *
     * @var DBServer
     */
    public $DBServer;
    public $launchFailureReason;

    public function __construct(DBServer $dbServer, $launchFailureReason)
    {
        parent::__construct();
        $this->DBServer = $dbServer;
        $this->launchFailureReason = $launchFailureReason;
    }

    public static function GetScriptingVars()
    {
        return array(
            "launch_failure_reason" => "launchFailureReason"
        );
    }
}
