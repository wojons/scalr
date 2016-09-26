<?php

class BeforeInstanceLaunchEvent extends AbstractServerEvent
{
    /**
     *
     * @var DBServer
     */
    public $DBServer;

    public $launchReason;
    public $launchReasonId;


    public function __construct(DBServer $DBServer, $reasonId = 0, $reasonMsg = "")
    {
        parent::__construct();

        $this->DBServer = $DBServer;

        try {
            $history = $this->DBServer->getServerHistory();
            $this->launchReasonId = $history->launchReasonId;
            $this->launchReason = $history->launchReason;
        } catch (Exception $e) {

        }
    }

    public static function GetScriptingVars()
    {
        return array(
            "launch_reason" => "launchReason",
            "launch_reason_code" => "launchReasonId"
        );
    }
}
