<?php

class BeforeHostTerminateEvent extends AbstractServerEvent
{
    /**
     *
     * @var DBServer
     */
    public $DBServer;

    public $terminationReasonId = 0;
    public $terminationReason = '';

    public $suspend;

    public function __construct(DBServer $DBServer, $suspend = false)
    {
        parent::__construct();

        $this->DBServer = $DBServer;
        $this->suspend = $suspend;

        if (!$this->suspend) {
            try {
                $history = $this->DBServer->getServerHistory();
                $this->terminationReasonId = $history->terminateReasonId;
                $this->terminationReason = $history->terminateReason;
            } catch (Exception $e) {}
        }
    }

    public static function GetScriptingVars()
    {
        return array(
            "termination_reason" => "terminationReason",
            "termination_reason_code" => "terminationReasonId",
            "is_suspend" => "suspend"
        );
    }
}
