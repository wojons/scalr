<?php

class Scalr_Dm_Source extends Scalr_Model
{
    protected $dbTableName = 'dm_sources';
    protected $dbPrimaryKey = "id";
    protected $dbMessageKeyNotFound = "Deployment source #%s not found in database";

    protected $dbPropertyMap = array(
        'id'			=> 'id',
        'env_id'		=> array('property' => 'envId', 'is_filter' => true),
        'type'			=> array('property' => 'type', 'is_filter' => true),
        'url'			=> array('property' => 'url', 'is_filter' => true),
        'auth_type'		=> array('property' => 'authType', 'is_filter' => false),
        'auth_info'		=> array('property' => 'authInfo', 'is_filter' => false)
    );

    const AUTHTYPE_PASSWORD = 'password';
    const AUTHTYPE_CERT		= 'certificate';

    const TYPE_SVN 			= 'svn';
    const TYPE_HTTP			= 'http';
    const TYPE_GIT			= 'git';

    public
        $id,
        $envId,
        $type,
        $url,
        $authType;

    protected $authInfo;


    static public function getIdByUrlAndAuth($url, $authInfo) {

        $eAuthInfo = \Scalr::getContainer()->crypto->encrypt(serialize($authInfo));

        return \Scalr::getDb()->GetOne("SELECT id FROM dm_sources WHERE `url`=? AND auth_info=? LIMIT 1", array($url, $eAuthInfo));
    }

    public function setAuthInfo(stdClass $authInfo = null)
    {
        $this->authInfo = $this->getCrypto()->encrypt(serialize($authInfo));
    }

    public function getAuthInfo()
    {
        return unserialize(trim($this->getCrypto()->decrypt($this->authInfo)));
    }
}
