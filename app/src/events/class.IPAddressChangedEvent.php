<?php

class IPAddressChangedEvent extends Event
{

    /**
     *
     * @var DBServer
     */
    public $DBServer;

    public $NewIPAddress;

    public $NewLocalIPAddress;

    public $OldPublicIPAddress;
    public $OldPrivateIPAddress;

    public function __construct(DBServer $DBServer, $NewIPAddress, $NewLocalIPAddress = null)
    {
        parent::__construct();
        $this->DBServer = $DBServer;
        $this->NewIPAddress = $NewIPAddress;
        $this->NewLocalIPAddress = $NewLocalIPAddress;

        $this->OldPublicIPAddress = $DBServer->remoteIp;
        $this->OldPrivateIPAddress = $DBServer->localIp;
    }

    public static function GetScriptingVars()
    {
        return array(

            /** deprecated **/
            "new_ip_address" => "NewIPAddress",
            "new_local_ip_address" => "NewLocalIPAddress",

            "new_public_ip" => "NewIPAddress",
            "new_private_ip" => "NewLocalIPAddress",
            "old_public_ip" => "OldPublicIPAddress",
            "old_private_ip" => "OldPrivateIPAddress"
        );
    }

    public function getTextDetails()
    {
        return "IP address for instance {$this->DBServer->serverId} has been changed to {$this->NewIPAddress}";
    }
}
