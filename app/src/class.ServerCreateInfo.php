<?php

use Scalr\Modules\PlatformFactory;
use Scalr\Model\Entity;

class ServerCreateInfo
{
    public $platform;

    /**
     * @var DBFarmRole
     */
    public $dbFarmRole;
    public $index;
    public $remoteIp;
    public $localIp;
    public $clientId;
    public $envId;
    public $roleId;
    public $farmId;

    private $platformProps = array();

    private $properties;

    /**
     * Constructor
     *
     * @param    string     $platform    Platform
     * @param    DBFarmRole $DBFarmRole  optional Farm Role object
     * @param    int        $index       optional Server index within the Farm Role scope
     * @param    string     $role_id     optional Identifier of the Role
     */
    public function __construct($platform, DBFarmRole $DBFarmRole = null, $index = null, $role_id = null)
    {
        $this->platform = $platform;
        $this->dbFarmRole = $DBFarmRole;
        $this->index = $index;
        $this->roleId = $role_id === null ? $this->dbFarmRole->RoleID : $role_id;

        if ($DBFarmRole) {
            $this->envId = $DBFarmRole->GetFarmObject()->EnvID;
        }

        //Refletcion
        $Reflect = new ReflectionClass(DBServer::$platformPropsClasses[$this->platform]);
        foreach ($Reflect->getConstants() as $k => $v) {
            $this->platformProps[] = $v;
        }

        if ($DBFarmRole) {
            if (PlatformFactory::isOpenstack($this->platform)) {
                $this->SetProperties(array(
                    OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION => $DBFarmRole->CloudLocation
                ));
            } elseif(PlatformFactory::isCloudstack($this->platform)) {
                $this->SetProperties(array(
                    CLOUDSTACK_SERVER_PROPERTIES::CLOUD_LOCATION => $DBFarmRole->CloudLocation
                ));
            } else {
                switch($this->platform) {
                	case SERVER_PLATFORMS::GCE:
                	    $this->SetProperties(array(
                	       GCE_SERVER_PROPERTIES::CLOUD_LOCATION => $DBFarmRole->CloudLocation
                	    ));
                	    break;

                	case SERVER_PLATFORMS::EC2:
                	    $this->SetProperties(array(
                    	    //EC2_SERVER_PROPERTIES::AVAIL_ZONE => $DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_AVAIL_ZONE),
                    	    EC2_SERVER_PROPERTIES::REGION => $DBFarmRole->CloudLocation
                	    ));
                	    break;
                }
            }
        }

        $this->SetProperties(array(SERVER_PROPERTIES::SZR_VESION => '0.20.0'));
    }

    public function SetProperties(array $props)
    {
        foreach ($props as $k => $v) {
            if (in_array($k, $this->platformProps))
                $this->properties[$k] = $v;
            else
                throw new Exception(sprintf("Unknown property '%s' for server on '%s'", $k, $this->platform));
        }
    }

    public function GetProperty($propName)
    {
        if (in_array($propName, $this->platformProps))
            return $this->properties[$propName];
        else
            throw new Exception(sprintf("Unknown property '%s' for server on '%s'", $propName, $this->platform));
    }

    public function GetProperties()
    {
        return $this->properties;
    }
}

