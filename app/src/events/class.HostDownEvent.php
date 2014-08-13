<?php

class HostDownEvent extends Event
{

    /**
     * @var DBServer
     */
    public $DBServer;

    public $terminationReasonId = 0;
    public $terminationReason = '';

    /**
     *
     * @var DBServer
     */
    public $replacementDBServer;

    public function __construct(DBServer $DBServer)
    {
        parent::__construct();
        $this->DBServer = $DBServer;
        $r_server = \Scalr::getDb()->GetRow("SELECT server_id FROM servers WHERE replace_server_id=? LIMIT 1", array(
            $DBServer->serverId
        ));
        if ($r_server) {
            $this->replacementDBServer = DBServer::LoadByID($r_server['server_id']);
        }

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
        );
    }

    public function getTextDetails()
    {
        return "Instance {$this->DBServer->serverId} ({$this->DBServer->remoteIp}) Internal IP: {$this->DBServer->localIp} terminated";
    }
}
