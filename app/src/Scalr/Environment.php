<?php

use Scalr\Modules\Platforms\Cloudstack\CloudstackPlatformModule;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Modules\Platforms\Eucalyptus\EucalyptusPlatformModule;
use Scalr\Modules\Platforms\GoogleCE\GoogleCEPlatformModule;
use Scalr\Modules\Platforms\Idcf\IdcfPlatformModule;
use Scalr\Modules\Platforms\Nimbula\NimbulaPlatformModule;
use Scalr\Modules\Platforms\Openstack\OpenstackPlatformModule;
use Scalr\Modules\Platforms\Rackspace\RackspacePlatformModule;
use Scalr\Modules\Platforms\UCloud\UCloudPlatformModule;

/**
 * Scalr_Environment class
 *
 * Following phpdocumentor comments have been derived from Scalr\DependencyInjection class:
 *
 * @property string $awsRegion
 *           The AWS region derived from user's environment.
 *
 * @property string $awsSecretAccessKey
 *           The AWS sercret access key taken from user's environment.
 *
 * @property string $awsAccessKeyId
 *           The Aws access key id taken from user's environment.
 *
 * @property string $awsAccountNumber
 *           The Aws account number.
 *
 * @property \Scalr_Session $session
 *           The Scalr Session isntance.
 *
 * @property \Scalr\Service\Cloudyn $cloudyn
 *           The Cloudyn instance for the current user
 *
 * @property \Scalr\Service\Aws $aws
 *           The Aws instance for the last instantiated user's environment.
 *
 * @property \Scalr_UI_Request $request
 *           The Scalr_UI_Request instance.
 *
 * @property \Scalr_Account_User $user
 *           The Scalr_Account_User instance which is property for the request.
 *
 * @property \Scalr\Logger\AuditLog $auditLog
 *           The AuditLog.
 *
 * @property \Scalr\Logger\LoggerStorageInterface $auditLogStorage
 *           The AuditLogStorage
 *
 * @property \Scalr\SimpleMailer $mailer
 *           Returns the new instance of the SimpleMailer class.
 *           This is not a singletone.
 *
 * @property \Scalr\System\Config\Yaml $config
 *           Gets configuration
 *
 * @property \ADODB_mysqli $adodb
 *           Gets an ADODB mysqli Connection object
 *
 * @property \ADODB_mysqli $dnsdb
 *           Gets an ADODB mysqli Connection to PDNS Database
 *
 * @property \ADODB_mysqli $cadb
 *           Gets an ADODB mysqli Connection to Cost Analytics database
 *
 * @property \Scalr\Acl\Acl $acl
 *           Gets an ACL shared service
 *
 * @property \Scalr\DependencyInjection\AnalyticsContainer $analytics
 *           Gets Cost Analytics sub container
 *
 *
 * @method   mixed config()
 *           config(string $name)
 *           Gets config value for the dot notation access key
 *
 *
 * @method   \Scalr_Environment loadById()
 *           loadById($id)
 *           Loads Scalr_Environment object using unique identifier.
 *
 * @method   \ADODB_mysqli adodb()
 *           adodb()
 *           Gets an ADODB mysqli Connection object
 *
 * @method   \Scalr\Net\Ldap\LdapClient ldap()
 *           ldap($user, $password)
 *           Gets a new instance of LdapClient for specified user
 */
class Scalr_Environment extends Scalr_Model
{

    protected $dbTableName = "client_environments";
    protected $dbPropertyMap = array(
        'id'		=> 'id',
        'name'		=> array('property' => 'name', 'is_filter' => true),
        'client_id'	=> array('property' => 'clientId', 'is_filter' => true),
        'dt_added'	=> array('property' => 'dtAdded', 'createSql' => 'NOW()', 'type' => 'datetime', 'update' => false),
        'status'    => 'status'
    );

    public
        $id,
        $name,
        $clientId,
        $dtAdded,
        $status;

    private $cache = array();

    /**
     * Encrypted variables list
     *
     * It looks like array(variable => true)
     * This array is initialized by the getEncryptedVariables method
     * and should not be used directly.
     *
     * @var array
     */
    private static $encryptedVariables;

    const SETTING_TIMEZONE = 'timezone';
    const SETTING_UI_VARS  = 'ui.vars';

    const SETTING_CC_ID    = 'cc_id';

    const SETTING_CLOUDYN_ENABLED		= 'cloudyn.enabled';
    const SETTING_CLOUDYN_AWS_ACCESSKEY	= 'cloudyn.aws.accesskey';
    const SETTING_CLOUDYN_ACCOUNTID     = 'cloudyn.accountid';

    const SETTING_API_LIMIT_ENABLED     = 'api.limit.enabled';
    const SETTING_API_LIMIT_REQPERHOUR  = 'api.limit.requests_per_hour';
    const SETTING_API_LIMIT_HOUR        = 'api.limit.hour';
    const SETTING_API_LIMIT_USAGE       = 'api.limit.usage';

    const STATUS_ACTIVE    = 'Active';
    const STATUS_INACTIVE  = 'Inactive';

    /**
     * @param  string  $id  serviceid
     * @return mixed
     */
    public function __get($id)
    {
        if ($this->getContainer()->initialized($id)) {
            return $this->get($id);
        }
        throw new \RuntimeException(sprintf('Missing property "%s" for the %s.', $id, get_class($this)));
    }

    /**
     * {@inheritdoc}
     * @see Scalr_Model::__call()
     */
    public function __call($name, $arguments)
    {
        if ($this->getContainer()->initialized($name, true)) {
            return call_user_func_array(array($this->getContainer(), $name), $arguments);
        } else {
            return parent::__call($name, $arguments);
        }
    }

    /**
     * Gets an Amazon Web Service (Aws) factory instance
     *
     * This method ensures that aws instance is always from the
     * current environment scope.
     *
     * @param   string|\DBServer|\DBFarmRole|\DBEBSVolume $awsRegion optional
     *          The region or object which has both Scalr_Environment instance and cloud location itself
     *
     * @param   string  $awsAccessKeyId     optional The AccessKeyId
     * @param   string  $awsSecretAccessKey optional The SecretAccessKey
     * @param   string  $certificate        optional Contains x.509 certificate
     * @param   string  $privateKey         optional The private key for the certificate
     * @return  \Scalr\Service\Aws Returns Aws instance
     */
    public function aws($awsRegion = null, $awsAccessKeyId = null, $awsSecretAccessKey = null,
                        $certificate = null, $privateKey = null)
    {
        $arguments = func_get_args();
        if (count($arguments) <= 1) {
            $arguments[0] = isset($arguments[0]) ? $arguments[0] : null;
            //Adds Scalr_Environment as second parameter
            $arguments[1] = $this;
        }
        //Retrieves an instance from the DI container
        return $this->__call('aws', $arguments);
    }

    /**
     * Gets Eucalyptus cloud client
     *
     * @param   string|\DBServer|\DBFarmRole    $cloudLocation
     *          The cloud location or object which has both Scalr_Environment instance and cloud location itself
     *
     * @return  \Scalr\Service\Eucalyptus Returns Eucalyptus instance
     */
    public function eucalyptus($cloudLocation)
    {
        $arguments = func_get_args();
        $arguments[0] = $cloudLocation;
        $arguments[1] = $this;

        return $this->__call('eucalyptus', $arguments);
    }

    /**
     * Gets an OpenStack client instance
     *
     * This method ensures that openstack instance is always from the current
     * environment scope
     *
     * @param   \Scalr\Service\OpenStack\OpenStackConfig|string   $platform  The platform name or Openstack config
     * @param   string                                            $region    optional The region
     * @return  \Scalr\Service\OpenStack\OpenStack Returns openstack instance from DI container
     */
    public function openstack($platform, $region = null)
    {
        $arguments = func_get_args();
        for ($i = 0; $i < 2; ++$i) {
            if (!isset($arguments[$i])) {
                $arguments[$i] = null;
            }
        }
        //Adds Scalr_Environment as third parameter
        $arguments[2] = $this;
        //Retrieves an instance from the DI container
        return $this->__call('openstack', $arguments);
    }

    /**
     * Gets an CloudStack client instance
     *
     * This method ensures that cloudstack instance is always from the current
     * environment scope
     *
     * @param   string   $platform     Platform name
     * @param   string   $apiUrl       The endpoint name url
     * @param   string   $apiKey       Api key
     * @param   string   $secretKey    Secret key
     * @return  \Scalr\Service\CloudStack\CloudStack Returns cloudstack instance from DI container
     */
    public function cloudstack($platform = 'cloudstack', $apiUrl = null, $apiKey = null, $secretKey = null)
    {
        $arguments = func_get_args();
        if (count($arguments) <= 1) {
            $arguments[0] = isset($arguments[0]) ? $arguments[0] : null;
            //Adds Scalr_Environment as second parameter
            $arguments[1] = $this;
        }
        //Retrieves an instance from the DI container
        return $this->__call('cloudstack', $arguments);
    }

    /**
     * Gets an service or parameter by its id.
     *
     * @param   string  $serviceid
     * @return  mixed
     * @throws  \RuntimeException
     */
    public function get($serviceid)
    {
        if ($this->getContainer()->initialized($serviceid)) {
            return $this->getContainer()->get($serviceid);
        }
        throw new \RuntimeException(sprintf('Service "%s" has not been initialized.', $serviceid));
    }

    /**
     * Init
     *
     * @param   string $className
     * @return  \Scalr_Environment
     */
    public static function init($className = null)
    {
        return parent::init();
    }

    public function create($name, $clientId)
    {
        $this->id = 0;
        $this->name = $name;
        $this->clientId = $clientId;
        $this->status = self::STATUS_ACTIVE;
        $this->save();

        if (\Scalr::getContainer()->analytics->enabled) {
            $ccId = \Scalr::getContainer()->analytics->usage->autoCostCentre($this->id);
            $this->setPlatformConfig([Scalr_Environment::SETTING_CC_ID => $ccId]);

            \Scalr::getContainer()->analytics->events->fireAssignCostCenterEvent($this, $ccId);
        }

        return $this;
    }

    protected function encryptValue($value)
    {
        return $this->getCrypto()->encrypt($value, $this->cryptoKey);
    }

    protected function decryptValue($value)
    {
        return $this->getCrypto()->decrypt($value, $this->cryptoKey);
    }

    public function loadDefault($clientId)
    {
        // TODO: rewrite Scalr_Environment::loadDefault($clientId) for user-based
        $info = $this->db->GetRow("SELECT * FROM client_environments WHERE client_id = ? LIMIT 1", array($clientId));
        if (! $info)
            throw new Exception(sprintf(_('Default environment for clientId #%s not found'), $clientId));

        return $this->loadBy($info);
    }

    /**
     * Gets client_environment_properties value.
     *
     * @return  mixed      Returns config value on success or NULL if value does not exist.
     */
    public function getFullConfiguration()
    {
        $mustBeEncrypted = self::getEncryptedVariables();
        $res = $this->db->Execute("SELECT `name`, `value`, `group` FROM client_environment_properties WHERE env_id = ?", array($this->id));
        while ($item = $res->FetchRow()) {
            if (isset($mustBeEncrypted[$item['name']]) && $item['value'] !== null) {
                $item['value'] = $this->decryptValue($item['value']);
            }
            $this->cache[$item['group']][$item['name']] = $item['value'] !== false ? $item['value'] : null;
        }

        return $this->cache;
    }

    /**
     * Gets client_environment_properties value.
     *
     * @param   string     $key       Property name.
     * @param   bool       $encrypted optional This value is ignored and never taken into account
     * @param   string     $group     optional Group name.
     * @return  mixed      Returns config value on success or NULL if value does not exist.
     */
    public function getPlatformConfigValue($key, $encrypted = true, $group = '')
    {
        $varlinks = self::getLinkedVariables();
        if (!isset($this->cache[$group]) || !array_key_exists($key, $this->cache[$group])) {
            $mustBeEncrypted = self::getEncryptedVariables();
            $keys = isset($varlinks[$key]) ? self::getLinkedVariables($varlinks[$key]) : array($key);
            $args = array_merge(array($this->id, $group), $keys);
            $res = $this->db->GetAssoc("
                SELECT name, value
                FROM client_environment_properties
                WHERE env_id = ? AND `group` = ?
                AND name IN (" . join(', ', array_fill(0, count($keys), '?')) . ")
            ", $args, true, true);
            foreach ($keys as $k) {
                $value = isset($res[$k]) ? $res[$k] : null;
                if (isset($mustBeEncrypted[$k]) && $value !== null) {
                    $value = $this->decryptValue($value);
                }
                $this->cache[$group][$k] = $value !== false ? $value : null;
            }
        }

        return $this->cache[$group][$key];
    }

    public function isPlatformEnabled($platform)
    {
        // constant from SERVER_PLATFORMS class
        return $this->getPlatformConfigValue($platform . '.is_enabled', false);
    }

    public function getEnabledPlatforms()
    {
        $enabled = array();
        foreach (array_keys(SERVER_PLATFORMS::getList()) as $value) {
            if ($this->isPlatformEnabled($value))
                $enabled[] = $value;
        }
        return $enabled;
    }

    public function getLocations()
    {
        if (!$this->cache['locations']) {
            $this->cache['locations'] = array();
            foreach ($this->getEnabledPlatforms() as $platform) {
                $class = 'Scalr\\Modules\\Platforms\\' . ucfirst($platform) . '\\' . ucfirst($platform) . 'PlatformModule';
                $locs = call_user_func(array($class, "getLocations"), $this);
                foreach ($locs as $k => $v)
                    $this->cache['locations'][$k] = $v;
            }
        }

        krsort($this->cache['locations']);

        return $this->cache['locations'];
    }

    public function enablePlatform($platform, $enabled = true)
    {
        $props = array($platform . '.is_enabled' => $enabled ? 1 : 0);
        if (!$enabled) {
            foreach ($this->getLinkedVariables($platform) as $key)
                $props[$key] = null;
        }

        $this->setPlatformConfig($props, false);
        $this->cache['locations'] = null;
    }

    /**
     * Saves platform config value to database.
     *
     * This operation will update client_environment_properties table or delete if value is null.
     *
     * @param   array        $props    List of properties with its values keypairs to save.
     * @param   bool         $encrypt  optional This value is ignored and never taken into account.
     * @param   string       $group    Group
     * @throws  Exception
     */
    public function setPlatformConfig($props, $encrypt = true, $group = '')
    {
        $mustBeEncrypted = self::getEncryptedVariables();
        $updates = array();
        foreach ($props as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $key2 => $value2) {
                    $updates[$key2] = ($value2 === false ? null : $value2);
                }
            } else {
                $updates[$key] = ($value === false ? null : $value);
            }
        }
        foreach ($updates as $key => $value) {
            //Updates the cache
            $this->cache[$group][$key] = $value;
            if ($value === false)
                $value = 0;

            if (isset($mustBeEncrypted[$key]) && $value !== null) {
                $value = $this->encryptValue($value);
            }

            try {
                if ($value === null) {
                    $this->db->Execute("
                        DELETE FROM client_environment_properties
                        WHERE env_id = ? AND name = ? AND `group` = ?
                    ", array($this->id, $key, $group));
                } else {
                    $this->db->Execute("
                        INSERT INTO client_environment_properties
                        SET env_id = ?, name = ?, value = ?, `group` = ?
                        ON DUPLICATE KEY UPDATE value = ?
                    ", array($this->id, $key, $value, $group, $value));
                }
            } catch (Exception $e) {
                throw new Exception (sprintf(_("Cannot update record. Error: %s"), $e->getMessage()), $e->getCode());
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see Scalr_Model::save()
     */
    public function save($forceInsert = false)
    {
        if ($this->db->GetOne("SELECT id FROM client_environments WHERE name = ? AND client_id = ? AND id != ? LIMIT 1", array($this->name, $this->clientId, $this->id)))
            throw new Exception('This name is already used');

        parent::save();

        if ($this->id && \Scalr::getContainer()->analytics->enabled) {
            \Scalr::getContainer()->analytics->tags->syncValue(
                $this->clientId, \Scalr\Stats\CostAnalytics\Entity\TagEntity::TAG_ID_ENVIRONMENT, $this->id, $this->name
            );
        }
    }

    /**
     * {@inheritdoc}
     * @see Scalr_Model::delete()
     */
    public function delete($id = null)
    {
        if ($this->db->GetOne("SELECT COUNT(*) FROM farms WHERE env_id = ?", array($this->id)))
            throw new Exception("Cannot remove environment. You need to remove all your farms first.");

        if ($this->db->GetOne("SELECT COUNT(*) FROM client_environments WHERE client_id = ?", array($this->clientId)) < 2)
            throw new Exception('At least one environment should be in account. You cannot remove the last one.');

        parent::delete();

        try {
            $this->db->Execute("DELETE FROM client_environment_properties WHERE env_id=?", array($this->id));
            $this->db->Execute("DELETE FROM apache_vhosts WHERE env_id=?", array($this->id));
            $this->db->Execute("DELETE FROM autosnap_settings WHERE env_id=?", array($this->id));
            $this->db->Execute("DELETE FROM bundle_tasks WHERE env_id=?", array($this->id));
            $this->db->Execute("DELETE FROM dm_applications WHERE env_id=?", array($this->id));
            $this->db->Execute("DELETE FROM dm_deployment_tasks WHERE env_id=?", array($this->id));

            $this->db->Execute("DELETE FROM dm_sources WHERE env_id=?", array($this->id));
            $this->db->Execute("DELETE FROM dns_zones WHERE env_id=?", array($this->id));
            $this->db->Execute("DELETE FROM ec2_ebs WHERE env_id=?", array($this->id));
            $this->db->Execute("DELETE FROM elastic_ips WHERE env_id=?", array($this->id));
            $this->db->Execute("DELETE FROM farms WHERE env_id=?", array($this->id));
            $this->db->Execute("DELETE FROM roles WHERE env_id=?", array($this->id));
            $this->db->Execute("DELETE FROM servers WHERE env_id=?", array($this->id));

            $this->db->Execute('DELETE FROM `account_team_envs` WHERE env_id = ?', array($this->id));

        } catch (Exception $e) {
            throw new Exception (sprintf(_("Cannot delete record. Error: %s"), $e->getMessage()), $e->getCode());
        }
    }

    public function getTeams()
    {
        return $this->db->getCol('SELECT team_id FROM `account_team_envs` WHERE env_id = ?', array($this->id));
    }

    public function clearTeams()
    {
        $this->db->Execute('DELETE FROM `account_team_envs` WHERE env_id = ?', array($this->id));
    }

    public function addTeam($teamId)
    {
        $team = Scalr_Account_Team::init()->loadById($teamId);

        if ($team->accountId == $this->clientId) {
            $this->removeTeam($teamId);
            $this->db->Execute('INSERT INTO `account_team_envs` (team_id, env_id) VALUES(?,?)', array(
                $teamId, $this->id
            ));
        } else
            throw new Exception('This team doesn\'t belongs to this account');
    }

    public function removeTeam($teamId)
    {
        $this->db->Execute('DELETE FROM `account_team_envs` WHERE env_id = ? AND team_id = ?', array($this->id, $teamId));
    }

    /**
     * Gets the list of the variables which need to be encrypted when we store them to database.
     *
     * @return  array Returns the array of variables looks like array(variablename => true);
     */
    private static function getEncryptedVariables()
    {
        if (!isset(self::$encryptedVariables)) {
            $cfg = array(
                SERVER_PLATFORMS::CLOUDSTACK . "." . CloudstackPlatformModule::API_KEY,
                SERVER_PLATFORMS::CLOUDSTACK . "."  . CloudstackPlatformModule::API_URL,
                SERVER_PLATFORMS::CLOUDSTACK . "."  . CloudstackPlatformModule::SECRET_KEY,

                SERVER_PLATFORMS::IDCF . "." . IdcfPlatformModule::API_KEY,
                SERVER_PLATFORMS::IDCF . "." . IdcfPlatformModule::API_URL,
                SERVER_PLATFORMS::IDCF . "." . IdcfPlatformModule::SECRET_KEY,

                SERVER_PLATFORMS::UCLOUD . "." . UCloudPlatformModule::API_KEY,
                SERVER_PLATFORMS::UCLOUD . "." . UCloudPlatformModule::API_URL,
                SERVER_PLATFORMS::UCLOUD . "." . UCloudPlatformModule::SECRET_KEY,

                SERVER_PLATFORMS::OPENSTACK . "." . OpenstackPlatformModule::API_KEY,
                SERVER_PLATFORMS::OPENSTACK . "." . OpenstackPlatformModule::AUTH_TOKEN,
                SERVER_PLATFORMS::OPENSTACK . "." . OpenstackPlatformModule::KEYSTONE_URL,
                SERVER_PLATFORMS::OPENSTACK . "." . OpenstackPlatformModule::PASSWORD,
                SERVER_PLATFORMS::OPENSTACK . "." . OpenstackPlatformModule::TENANT_NAME,
                SERVER_PLATFORMS::OPENSTACK . "." . OpenstackPlatformModule::USERNAME,
                SERVER_PLATFORMS::OPENSTACK . "." . OpenstackPlatformModule::SSL_VERIFYPEER,

                SERVER_PLATFORMS::OCS . "." . OpenstackPlatformModule::API_KEY,
                SERVER_PLATFORMS::OCS . "." . OpenstackPlatformModule::AUTH_TOKEN,
                SERVER_PLATFORMS::OCS . "." . OpenstackPlatformModule::KEYSTONE_URL,
                SERVER_PLATFORMS::OCS . "." . OpenstackPlatformModule::PASSWORD,
                SERVER_PLATFORMS::OCS . "." . OpenstackPlatformModule::TENANT_NAME,
                SERVER_PLATFORMS::OCS . "." . OpenstackPlatformModule::USERNAME,
                SERVER_PLATFORMS::OCS . "." . OpenstackPlatformModule::SSL_VERIFYPEER,

                SERVER_PLATFORMS::NEBULA . "." . OpenstackPlatformModule::API_KEY,
                SERVER_PLATFORMS::NEBULA . "." . OpenstackPlatformModule::AUTH_TOKEN,
                SERVER_PLATFORMS::NEBULA . "." . OpenstackPlatformModule::KEYSTONE_URL,
                SERVER_PLATFORMS::NEBULA . "." . OpenstackPlatformModule::PASSWORD,
                SERVER_PLATFORMS::NEBULA . "." . OpenstackPlatformModule::TENANT_NAME,
                SERVER_PLATFORMS::NEBULA . "." . OpenstackPlatformModule::USERNAME,
                SERVER_PLATFORMS::NEBULA . "." . OpenstackPlatformModule::SSL_VERIFYPEER,

                SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::API_KEY,
                SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::AUTH_TOKEN,
                SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::KEYSTONE_URL,
                SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::PASSWORD,
                SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::TENANT_NAME,
                SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::USERNAME,
                SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::SSL_VERIFYPEER,

                SERVER_PLATFORMS::CONTRAIL . "." . OpenstackPlatformModule::API_KEY,
                SERVER_PLATFORMS::CONTRAIL . "." . OpenstackPlatformModule::AUTH_TOKEN,
                SERVER_PLATFORMS::CONTRAIL . "." . OpenstackPlatformModule::KEYSTONE_URL,
                SERVER_PLATFORMS::CONTRAIL . "." . OpenstackPlatformModule::PASSWORD,
                SERVER_PLATFORMS::CONTRAIL . "." . OpenstackPlatformModule::TENANT_NAME,
                SERVER_PLATFORMS::CONTRAIL . "." . OpenstackPlatformModule::USERNAME,
                SERVER_PLATFORMS::CONTRAIL . "." . OpenstackPlatformModule::SSL_VERIFYPEER,

                SERVER_PLATFORMS::RACKSPACENG_UK . "." . OpenstackPlatformModule::API_KEY,
                SERVER_PLATFORMS::RACKSPACENG_UK . "." . OpenstackPlatformModule::AUTH_TOKEN,
                SERVER_PLATFORMS::RACKSPACENG_UK . "." . OpenstackPlatformModule::KEYSTONE_URL,
                SERVER_PLATFORMS::RACKSPACENG_UK . "." . OpenstackPlatformModule::PASSWORD,
                SERVER_PLATFORMS::RACKSPACENG_UK . "." . OpenstackPlatformModule::TENANT_NAME,
                SERVER_PLATFORMS::RACKSPACENG_UK . "." . OpenstackPlatformModule::USERNAME,

                SERVER_PLATFORMS::RACKSPACENG_US . "." . OpenstackPlatformModule::API_KEY,
                SERVER_PLATFORMS::RACKSPACENG_US . "." . OpenstackPlatformModule::AUTH_TOKEN,
                SERVER_PLATFORMS::RACKSPACENG_US . "." . OpenstackPlatformModule::KEYSTONE_URL,
                SERVER_PLATFORMS::RACKSPACENG_US . "." . OpenstackPlatformModule::PASSWORD,
                SERVER_PLATFORMS::RACKSPACENG_US . "." . OpenstackPlatformModule::TENANT_NAME,
                SERVER_PLATFORMS::RACKSPACENG_US . "." . OpenstackPlatformModule::USERNAME,

                Ec2PlatformModule::ACCESS_KEY,
                Ec2PlatformModule::ACCOUNT_ID,
                Ec2PlatformModule::CERTIFICATE,
                Ec2PlatformModule::PRIVATE_KEY,
                Ec2PlatformModule::SECRET_KEY,

                EucalyptusPlatformModule::ACCESS_KEY,
                EucalyptusPlatformModule::ACCOUNT_ID,
                EucalyptusPlatformModule::CERTIFICATE,
                EucalyptusPlatformModule::CLOUD_CERTIFICATE,
                EucalyptusPlatformModule::EC2_URL,
                EucalyptusPlatformModule::PRIVATE_KEY,
                EucalyptusPlatformModule::S3_URL,
                EucalyptusPlatformModule::SECRET_KEY,

                GoogleCEPlatformModule::ACCESS_TOKEN,
                GoogleCEPlatformModule::CLIENT_ID,
                GoogleCEPlatformModule::KEY,
                GoogleCEPlatformModule::PROJECT_ID,
                GoogleCEPlatformModule::SERVICE_ACCOUNT_NAME,

                NimbulaPlatformModule::API_URL,
                NimbulaPlatformModule::IMAGE_LIST_ENTRY_VALUE,
                NimbulaPlatformModule::PASSWORD,
                NimbulaPlatformModule::USERNAME,

                RackspacePlatformModule::API_KEY,
                RackspacePlatformModule::IS_MANAGED,
                RackspacePlatformModule::USERNAME,
            );
            self::$encryptedVariables = array_fill_keys($cfg, true);
        }
        return self::$encryptedVariables;
    }

    /**
     * Gets array of the linked variables
     *
     * @param   string  $linkid  optional If provided it will return variables from given group id
     * @return  array If linkid is null it will return array looks like array(variable => linkid).
     *                If linkid is provided it will return list of the linked variables
     *                from the specified group. array(variable1, variable2, ..., variableN)
     */
    private static function getLinkedVariables($linkid = null)
    {
        static $ret = array(), $rev = array();
        if (empty($ret)) {
            //Performs at once
            $ret = array(
                SERVER_PLATFORMS::EC2 => array(
                    Ec2PlatformModule::ACCESS_KEY,
                    Ec2PlatformModule::SECRET_KEY,
                    Ec2PlatformModule::CERTIFICATE,
                    Ec2PlatformModule::PRIVATE_KEY,
                    Ec2PlatformModule::ACCOUNT_ID,
                    Ec2PlatformModule::ACCOUNT_TYPE,
                ),
                SERVER_PLATFORMS::GCE => array(
                    GoogleCEPlatformModule::ACCESS_TOKEN,
                    GoogleCEPlatformModule::CLIENT_ID,
                    GoogleCEPlatformModule::KEY,
                    GoogleCEPlatformModule::PROJECT_ID,
                    GoogleCEPlatformModule::RESOURCE_BASE_URL,
                    GoogleCEPlatformModule::SERVICE_ACCOUNT_NAME
                ),
                'enabledPlatforms' => array()
            );

            foreach (array_keys(SERVER_PLATFORMS::getList()) as $value) {
                $ret['enabledPlatforms'][] = "{$value}.is_enabled";
            }

            foreach (array(SERVER_PLATFORMS::IDCF,
                SERVER_PLATFORMS::UCLOUD,
                SERVER_PLATFORMS::CLOUDSTACK) as $platform) {
                $ret[$platform] = array(
                    $platform . "." . CloudstackPlatformModule::ACCOUNT_NAME,
                    $platform . "." . CloudstackPlatformModule::API_KEY,
                    $platform . "." . CloudstackPlatformModule::API_URL,
                    $platform . "." . CloudstackPlatformModule::DOMAIN_ID,
                    $platform . "." . CloudstackPlatformModule::DOMAIN_NAME,
                    $platform . "." . CloudstackPlatformModule::SECRET_KEY,
                    $platform . "." . CloudstackPlatformModule::SHARED_IP,
                    $platform . "." . CloudstackPlatformModule::SHARED_IP_ID,
                    $platform . "." . CloudstackPlatformModule::SHARED_IP_INFO,
                    $platform . "." . CloudstackPlatformModule::SZR_PORT_COUNTER
                );
            }

            foreach (array(SERVER_PLATFORMS::OPENSTACK,
                           SERVER_PLATFORMS::ECS,
                           SERVER_PLATFORMS::OCS,
                           SERVER_PLATFORMS::NEBULA,
                           SERVER_PLATFORMS::RACKSPACENG_UK,
                           SERVER_PLATFORMS::RACKSPACENG_US) as $platform) {
                $ret[$platform] = array(
                    $platform . "." . OpenstackPlatformModule::API_KEY,
                    $platform . "." . OpenstackPlatformModule::AUTH_TOKEN,
                    $platform . "." . OpenstackPlatformModule::KEYSTONE_URL,
                    $platform . "." . OpenstackPlatformModule::PASSWORD,
                    $platform . "." . OpenstackPlatformModule::TENANT_NAME,
                    $platform . "." . OpenstackPlatformModule::USERNAME,
                );
            }
            //Computes fast access keys
            foreach ($ret as $platform => $linkedKeys) {
                foreach ($linkedKeys as $variable) {
                    $rev[$variable] = $platform;
                }
            }
        }
        return $linkid !== null ? (isset($ret[$linkid]) ? $ret[$linkid] : null) : $rev;
    }
}