<?php

class Scalr_Service_Apache_Vhost extends Scalr_Model
{
    protected $dbTableName = 'apache_vhosts';
    protected $dbPrimaryKey = "id";
    protected $dbMessageKeyNotFound = "Apache virtualhost #%s not found in database";

    /**
     * @var \DBFarm
     */
    private $dbFarm;

    protected $dbPropertyMap = array(
        'id'			=> 'id',
        'name'			=> array('property' => 'domainName', 'is_filter' => true),
        'is_ssl_enabled'=> array('property' => 'isSslEnabled', 'is_filter' => false),
        'env_id'		=> array('property' => 'envId', 'is_filter' => false),
        'client_id'		=> array('property' => 'clientId', 'is_filter' => false),
        'farm_id'		=> array('property' => 'farmId', 'is_filter' => false),
        'farm_roleid'	=> array('property' => 'farmRoleId', 'is_filter' => false),
        'ssl_cert_id'   => array('property' => 'sslCertId', 'is_filter' => false),
        'last_modified'=> array('property' => 'dtLastModified', 'createSql' => 'NOW()', 'updateSql' => 'NOW()', 'type' => 'datetime'),
        'httpd_conf'	=> array('property' => 'httpdConf', 'is_filter' => false),
        'httpd_conf_ssl'=> array('property' => 'httpdConfSsl', 'is_filter' => false),
        'httpd_conf_vars' => array('property' => 'templateOptions', 'is_filter' => false)
    );

    public $id,
        $domainName,
        $isSslEnabled,
        $envId,
        $clientId,
        $farmId,
        $farmRoleId,
        $dtLastModified,
        $sslCertId,
        $httpdConf,
        $httpdConfSsl,
        $templateOptions;

    /**
     * Creates new instance of the class
     *
     * @param   string       $className
     * @return  Scalr_Service_Apache_Vhost
     */
    public static function init($className = null)
    {
        return parent::init($className);
    }

    /**
     * Gets DBFarm object
     *
     * @return \DBFarm
     */
    public function getFarmObject()
    {
        if (!$this->dbFarm && !empty($this->farmId)) {
            $this->dbFarm = \DBFarm::LoadByID($this->farmId);
        }

        return $this->dbFarm;
    }

    /**
     * {@inheritdoc}
     * @see Scalr_Model::save()
     */
    public function save($forceInsert = false)
    {
        return parent::save($forceInsert);
    }
}