<?php

class HostDownEvent extends AbstractServerEvent
{

    /**
     * @var DBServer
     */
    public $DBServer;

    public $terminationReasonId = 0;
    public $terminationReason = '';
    
    public $isSuspended = false;

    
    public function __construct(DBServer $DBServer)
    {
        parent::__construct();
        $this->DBServer = $DBServer;

        try {
            $history = $this->DBServer->getServerHistory();
            $this->terminationReasonId = $history->terminateReasonId;
            $this->terminationReason = $history->terminateReason;
        } catch (Exception $e) {}
    }

    public static function GetScriptingVars()
    {
        return array(
            "termination_reason" => "terminationReason",
            "termination_reason_code" => "terminationReasonId",
            "is_suspend" => "isSuspended"
        );
    }

    public function getTextDetails()
    {
        return "Instance {$this->DBServer->serverId} ({$this->DBServer->remoteIp}) Internal IP: {$this->DBServer->localIp} terminated";
    }
}
