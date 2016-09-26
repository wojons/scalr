<?php

class FarmTerminatedEvent extends AbstractServerEvent
{
    public $RemoveZoneFromDNS;

    public $KeepElasticIPs;

    public $TermOnSyncFail;

    public $KeepEBS;

    public $ForceTerminate;

    public $userId;

    /**
     * Audit log extra fields
     *
     * @var array
     */
    public $auditLogExtra;

    /**
     * Constructor
     *
     * @param     int            $RemoveZoneFromDNS  Whether it should remove zone from DNS (1|0)
     * @param     int            $KeepElasticIPs     Whether it should keep elastic IPs (1|0)
     * @param     bool           $TermOnSyncFail     Whether it shuould terminate on sync fail
     * @param     int            $KeepEBS            Whether it should keep EBS
     * @param     bool           $ForceTerminate     optional Force termination
     * @param     int            $userId             optional Identifier of the User who terminates the Farm
     * @param     array          $auditLogExtra      optional Audit log extra fields
     */
    public function __construct(
        $RemoveZoneFromDNS,
        $KeepElasticIPs,
        $TermOnSyncFail,
        $KeepEBS,
        $ForceTerminate = true,
        $userId = null,
        $auditLogExtra = null
    ) {
        parent::__construct();

        $this->RemoveZoneFromDNS = $RemoveZoneFromDNS;
        $this->KeepElasticIPs = $KeepElasticIPs;
        $this->TermOnSyncFail = $TermOnSyncFail;
        $this->KeepEBS = $KeepEBS;
        $this->ForceTerminate = $ForceTerminate;
        $this->userId = $userId;
        $this->auditLogExtra = $auditLogExtra ?: [];
    }

    public function getTextDetails()
    {
        return "Farm has been terminated";
    }
}
