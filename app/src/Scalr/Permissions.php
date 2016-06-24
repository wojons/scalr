<?php
use Scalr\Acl\Acl;
use Scalr\DataType\AccessPermissionsInterface;

class Scalr_Permissions
{
    protected
        $user,
        $envId;

    /**
     * @param Scalr_Account_User $user
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Sets Environment Id
     *
     * @param   int     $envId
     * @return  Scalr_Permissions
     */
    public function setEnvironmentId($envId)
    {
        $this->envId = $envId;
        return $this;
    }

    public function validate($object)
    {
        if (!$this->check($object))
            throw new Scalr_Exception_InsufficientPermissions('Access denied');
    }

    public function check($object)
    {
        $cls = get_class($object);

        switch ($cls) {
            case 'Scalr_Environment':
                $flag = false;
                foreach ($this->user->getEnvironments() as $env) {
                    if ($env['id'] == $object->id) {
                        $flag = true;
                        break;
                    }
                }
                return $flag;

            case 'DBFarm':
                return $this->hasAccessFarm($object);

            case 'Scalr_Account_User':
                return ($object->getAccountId() == $this->user->getAccountId());

            case 'Scalr_Account_Team':
                return ($object->accountId == $this->user->getAccountId());

            case 'DBServer':
                return $this->hasAccessServer($object);

            case 'DBRole':
            case 'BundleTask':
            case 'DBDNSZone':
            case 'Scalr\Model\Entity\ScalingMetric':
            case 'Scalr_Service_Apache_Vhost':
            case 'Scalr_SchedulerTask':
            case 'Scalr\Model\Entity\SslCertificate':
            case 'Scalr_Db_Backup':
            case 'DBEBSVolume':
            case 'Scalr\Model\Entity\Role':
            case 'Scalr\Model\Entity\Image':
                return $this->hasAccessEnvironment($object->envId) &&
                       (method_exists($object, 'getFarmObject') ? $this->hasAccessFarm($object->getFarmObject()) : true);

            case 'DBFarmRole':
                return $this->hasAccessFarm($object->GetFarmObject());

            case 'Scalr\Acl\Role\AccountRoleObject':
                return $this->user->canManageAcl() &&
                       $object->getAccountId() == $this->user->getAccountId();

            default:
                if ($object instanceof AccessPermissionsInterface) {
                    return $object->hasAccessPermissions($this->user, Scalr_UI_Request::getInstance()->getEnvironment());
                }
        }
    }

    /**
     * Checks whether specified environment corresponds the user's environment.
     *
     * @param   int      $envId   The ID of the environment
     * @return  boolean  Returns true if user's environment equals to specified environment
     * @throws  \Scalr_Exception_Core
     */
    public function hasAccessEnvironment($envId)
    {
        if (is_null($this->envId)) {
            throw new \Scalr_Exception_Core('Identifier of the environment has not been defined for the ' . get_class($this));
        }

        return ($envId == $this->envId);
    }

    /**
     * Checks whether specified server can be accessed by the user
     *
     * @param   \DBServer $dbServer The DBServer object
     * @return  boolean   Returns true if specified server can be accessed by the user
     * @throws  \Scalr_Exception_Core
     */
    public function hasAccessServer(\DBServer $dbServer)
    {
        $access = $this->hasAccessEnvironment($dbServer->envId);

        if ($access) {
            if (!empty($dbServer->farmId)) {
                if (($dbFarm = $dbServer->GetFarmObject()) instanceof \DBFarm) {
                    $access = $this->hasAccessFarm($dbFarm, Acl::PERM_FARMS_SERVERS);
                }
            } else {
                $access = $this->user->getAclRolesByEnvironment($this->envId)->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE);
            }
        }

        return $access;
    }

    /**
     * Checks whether user read only access to specified server
     *
     * @param   \DBServer $dbServer The DBServer object
     * @return  boolean   Returns true if specified server can be accessed by the user
     * @throws  \Scalr_Exception_Core
     */
    public function hasReadOnlyAccessServer(\DBServer $dbServer)
    {
        $access = $this->hasAccessEnvironment($dbServer->envId);

        if ($access) {
            if (!empty($dbServer->farmId)) {
                if (($dbFarm = $dbServer->GetFarmObject()) instanceof \DBFarm) {
                    $access = $this->hasAccessFarm($dbFarm);
                }
            } else {
                $access = $this->user->getAclRolesByEnvironment($this->envId)->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE);
            }
        }

        return $access;
    }

    /**
     * Checks whether current dbFarm object can be accessed by user
     *
     * @param    \DBFarm               $dbFarm   DbFarm object
     * @param    string                $perm
     * @throws   Scalr_Exception_Core
     * @return   boolean         Returns true if access is granted
     */
    public function hasAccessFarm($dbFarm, $perm = null)
    {
        //It may not be provided in several cases
        if (!($dbFarm instanceof \DBFarm)) {
            return true;
        }

        if (!$this->hasAccessEnvironment($dbFarm->EnvID))
            return false;

        $superposition = $this->user->getAclRolesByEnvironment($this->envId);
        $result = $superposition->isAllowed(Acl::RESOURCE_FARMS, $perm);
        if (!$result && $dbFarm->__getNewFarmObject()->hasUserTeamOwnership($this->user)) {
            $result = $superposition->isAllowed(Acl::RESOURCE_TEAM_FARMS, $perm);
        }

        if (!$result && $dbFarm->ownerId && $this->user->id == $dbFarm->ownerId) {
            $result = $superposition->isAllowed(Acl::RESOURCE_OWN_FARMS, $perm);
        }

        return $result;
    }
}
