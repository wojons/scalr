<?php

use Scalr\DependencyInjection\Container;

/**
 * @method  \Scalr\Service\Aws aws() aws() Gets an Aws instance.
 */
class Scalr_Environment extends Scalr_Model
{
    /**
     * @var Container
     */
    private $container;

	protected $dbTableName = "client_environments";
	protected $dbPropertyMap = array(
		'id'		=> 'id',
		'name'		=> array('property' => 'name', 'is_filter' => true),
		'client_id'	=> array('property' => 'clientId', 'is_filter' => true),
		'dt_added'	=> array('property' => 'dtAdded', 'createSql' => 'NOW()', 'type' => 'datetime', 'update' => false),
		'is_system'	=> array('property' => 'isSystem', 'type' => 'bool'),
		'status'    => 'status',
		'color'     => 'color'
	);

	private $plainTextSettings = array(
		ENVIRONMENT_SETTINGS::TIMEZONE,
		ENVIRONMENT_SETTINGS::CLOUDYN_ENABLED,
		ENVIRONMENT_SETTINGS::CLOUDYN_AWS_ACCESSKEY,
		ENVIRONMENT_SETTINGS::CLOUDYN_ACCOUNTID,
	);

	public
		$id,
		$name,
		$clientId,
		$dtAdded,
		$isSystem,
		$status,
		$color;

	private $cache = array();

	const SETTING_TIMEZONE = 'timezone';

	const STATUS_ACTIVE = 'Active';
	const STATUS_INACTIVE = 'Inactive';

    /**
     * {@inheritdoc}
     * @see Scalr_Model::__construct()
     */
    public function __construct ($id = null)
    {
        parent::__construct($id);
        $this->container = Container::getInstance();
        if ($id !== null) {
            $this->container->environment = $this;
        }
    }

    /**
     * {@inheritdoc}
     * @see Scalr_Model::loadBy()
     */
    public function loadBy($info)
    {
        $object = parent::loadBy($info);
        $this->container->environment = $object;
        return $object;
    }

    /**
     * @param  string  $id  serviceid
     * @return mixed
     */
    public function __get($id)
    {
        if ($this->container->initialized($id)) {
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
    	if ($this->container->initialized($name, true)) {
    		return call_user_func_array(array($this->container, $name), $arguments);
        } else {
        	return parent::__call($name, $arguments);
        }
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
        if ($this->container->initialized($serviceid)) {
            return $this->container->get($serviceid);
        }
        throw new \RuntimeException(sprintf('Service "%s" has not been initialized.', $serviceid));
    }

    /**
     * Gets DependencyInjection Container
     *
     * @return \Scalr\DependencyInjection\Container Returns DI Container
     */
    public function getContainer ()
    {
        return $this->container;
    }

	/**
	 *
	 * @return Scalr_Environment
	 */
	public static function init() {
		return parent::init();
	}

	public function create($name, $clientId, $isSystem = false)
	{
		$this->id = 0;
		$this->name = $name;
		$this->clientId = $clientId;
		$this->isSystem = $isSystem;
		$this->status = self::STATUS_ACTIVE;
		$this->color = '';
		$this->save();
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

	public function loadByApiKeyId($keyId)
	{
		$id = $this->db->GetOne("SELECT env_id FROM client_environment_properties WHERE name = ? AND value = ?", array(
			ENVIRONMENT_SETTINGS::API_KEYID, $keyId
		));

		if ($id)
			return $this->loadById($id);
		else
			throw new Exception(sprintf(_("API KeyID '%s' not found in database"), $keyId));
	}

	public function loadDefault($clientId)
	{
		$info = $this->db->GetRow("SELECT * FROM client_environments WHERE client_id = ? AND is_system = 1", array($clientId));
		if (! $info)
			throw new Exception(sprintf(_('Default environment for clientId #%s not found'), $clientId));

		return $this->loadBy($info);
	}

	public function getPlatformConfigValue($key, $encrypted = true, $group = '')
	{
		if (in_array($key, $this->plainTextSettings))
			$encrypted = false;

		if (! isset($this->cache[$group][$key])) {
			$value = $this->db->GetOne("SELECT value FROM client_environment_properties WHERE env_id = ? AND name = ? AND `group` = ?", array($this->id, $key, $group));
			if ($encrypted)
				$value = $this->decryptValue($value);
			$this->cache[$group][$key] = $value ? $value : null;
		}

		return $this->cache[$group][$key];
	}

	public function setSystem()
	{
		$this->db->Execute("UPDATE client_environments SET is_system = 0 WHERE client_id = ?", array($this->clientId));
		$this->db->Execute("UPDATE client_environments SET is_system = 1 WHERE id = ?", array($this->id));
	}

	public function isPlatformEnabled($platform) // constant from SERVER_PLATFORMS class
	{
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
				$locs = call_user_func(array("Modules_Platforms_".ucfirst($platform), "getLocations"));
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
		if (! $enabled) {
			foreach (array_keys(call_user_func(array("Modules_Platforms_".ucfirst($platform), "getPropsList"))) as $key) {
				$props[$key] = null;
			}
		}

		$this->setPlatformConfig($props, false);
		$this->cache['locations'] = null;
	}

	public function setPlatformConfig($props, $encrypt = true, $group = '')
	{
		$updates = array();

		foreach ($props as $key => $value) {
			if (is_array($value)) {
				foreach ($value as $key2 => $value2) {
					$updates[$key2] = $value2;
				}
			} else {
				$updates[$key] = $value;
			}
		}

		foreach ($updates as $key => $value) {

			if (in_array($key, $this->plainTextSettings))
				$e = false;
			else
				$e = $encrypt;

			if ($e && $value)
				$value = $this->encryptValue($value);

			try {
				if (! $value)
					$this->db->Execute("DELETE FROM client_environment_properties WHERE env_id = ? AND name = ? AND `group` = ?", array($this->id, $key, $group));
				else
					$this->db->Execute("INSERT INTO client_environment_properties SET env_id = ?, name = ?, value = ?, `group` = ? ON DUPLICATE KEY UPDATE value = ?", array($this->id, $key, $value, $group, $value));
			} catch (Exception $e) {
				throw new Exception (sprintf(_("Cannot update record. Error: %s"), $e->getMessage()), $e->getCode());
			}
		}
	}

	public function save()
	{
		if ($this->db->GetOne('SELECT id FROM client_environments WHERE name = ? AND client_id = ? AND id != ?', array($this->name, $this->clientId, $this->id)))
			throw new Exception('This name already used');

		parent::save();
	}

	public function delete()
	{
		if ($this->isSystem)
			throw new Exception("You cannot remove system environment");

		if ($this->db->GetOne("SELECT COUNT(*) FROM farms WHERE env_id=?", array($this->id)))
			throw new Exception("Cannot remove environment. You need to remove all your farms first.");

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
			$this->db->Execute("DELETE FROM ssh_keys WHERE env_id=?", array($this->id));

			$this->db->Execute('DELETE FROM `account_team_envs` WHERE env_id = ?', array($this->id));

		} catch (Exception $e) {
			throw new Exception (sprintf(_("Cannot delete record. Error: %s"), $e->getMessage()), $e->getCode());
		}
	}
}

