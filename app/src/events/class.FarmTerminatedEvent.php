<?php

class FarmTerminatedEvent extends Event
{
    public $RemoveZoneFromDNS;
    public $KeepElasticIPs;
    public $TermOnSyncFail;
    public $KeepEBS;
    public $ForceTerminate;
    public $userId;

    public function __construct($RemoveZoneFromDNS, $KeepElasticIPs, $TermOnSyncFail, $KeepEBS, $ForceTerminate = true, $userId = null)
    {
        parent::__construct();

        $this->RemoveZoneFromDNS = $RemoveZoneFromDNS;
        $this->KeepElasticIPs = $KeepElasticIPs;
        $this->TermOnSyncFail = $TermOnSyncFail;
        $this->KeepEBS = $KeepEBS;
        $this->ForceTerminate = $ForceTerminate;
        $this->userId = $userId;
    }

    public function getTextDetails()
    {
        return "Farm has been terminated";
    }
}
