<?php

use Scalr\Acl\Acl;
use Scalr\Model\Entity;

class ScalrAPI_2_1_0 extends ScalrAPI_2_0_0
{
    public function FarmsList()
    {
        $response = parent::FarmsList();

        foreach ($response->FarmSet->Item as &$item)
            unset($item->Region);

        return $response;
    }

    public function FarmGetDetails($FarmID)
    {
        $response = parent::FarmGetDetails($FarmID);

        foreach ($response->FarmRoleSet->Item as &$item)
            $item->CloudLocation = DBFarmRole::LoadByID($item->ID)->CloudLocation;

        return $response;
    }

    public function ApacheVhostCreate($DomainName, $FarmID, $FarmRoleID, $DocumentRootDir, $EnableSSL, $SSLPrivateKey = null, $SSLCertificate = null)
    {
        $this->restrictAccess(Acl::RESOURCE_SERVICES_APACHE);

        $validator = new Scalr_Validator();

        if ($validator->validateDomain($DomainName) !== true)
            $err[] = _("Domain name is incorrect");

        $DBFarm = DBFarm::LoadByID($FarmID);
        if ($DBFarm->EnvID != $this->Environment->id)
            throw new Exception(sprintf("Farm #%s not found", $FarmID));

        $this->user->getPermissions()->validate($DBFarm);

        $DBFarmRole = DBFarmRole::LoadByID($FarmRoleID);
        if ($DBFarm->ID != $DBFarmRole->FarmID)
            throw new Exception(sprintf("FarmRole #%s not found on Farm #%s", $FarmRoleID, $FarmID));

        if (!$DocumentRootDir)
            throw new Exception(_("DocumentRootDir required"));

        $options = serialize(array(
            "document_root" 	=> trim($DocumentRootDir),
            "logs_dir"			=> "/var/log",
            "server_admin"		=> $this->user->getEmail()
        ));

        $httpConfigTemplateSSL = @file_get_contents(dirname(__FILE__)."/../../templates/services/apache/ssl.vhost.tpl");
        $httpConfigTemplate = @file_get_contents(dirname(__FILE__)."/../../templates/services/apache/nonssl.vhost.tpl");

        $vHost = Scalr_Service_Apache_Vhost::init();
        $vHost->envId = (int)$this->Environment->id;
        $vHost->clientId = $this->user->getAccountId();

        $vHost->domainName = $DomainName;
        $vHost->isSslEnabled = $EnableSSL ? true : false;
        $vHost->farmId = $FarmID;
        $vHost->farmRoleId = $FarmRoleID;

        $vHost->httpdConf = $httpConfigTemplate;

        $vHost->templateOptions = $options;

        $this->DB->BeginTrans();
        try {
            //SSL stuff
            if ($vHost->isSslEnabled) {
                $cert = new Entity\SslCertificate;
                $cert->envId = $DBFarm->EnvID;
                $cert->name = $DomainName;
                $cert->privateKey = base64_decode($SSLPrivateKey);
                $cert->certificate = base64_decode($SSLCertificate);
                $cert->save();

                $vHost->sslCertId = $cert->id;
                $vHost->httpdConfSsl = $httpConfigTemplateSSL;
            } else {
                $vHost->sslCertId = 0;
            }

            $vHost->save();
            $this->DB->CommitTrans();

        } catch (\Exception $e) {
            $this->DB->RollbackTrans();
            throw new Exception('Error saving VHost. ' . $e->getMessage(), $e->getCode(), $e);
        }

        $servers = $DBFarm->GetServersByFilter(array('status' => array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING)));
        foreach ($servers as $DBServer) {
            if ($DBServer->GetFarmRoleObject()->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::NGINX) ||
                $DBServer->GetFarmRoleObject()->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::APACHE))
                $DBServer->SendMessage(new Scalr_Messaging_Msg_VhostReconfigure());
        }

        $response = $this->CreateInitialResponse();
        $response->Result = 1;

        return $response;
    }

    public function ApacheVhostsList()
    {
        $this->restrictAccess(Acl::RESOURCE_SERVICES_APACHE);

        $response = $this->CreateInitialResponse();
        $response->ApacheVhostSet = new stdClass();
        $response->ApacheVhostSet->Item = array();

        $stmt = "SELECT v.* FROM apache_vhosts v LEFT JOIN farms f ON f.id = v.farm_id WHERE v.client_id = ? AND " . $this->getFarmSqlQuery();
        $args = array($this->user->getAccountId());

        $rows = $this->DB->Execute($stmt, $args);
        while ($row = $rows->FetchRow()) {
            $itm = new stdClass();
            $itm->Name = $row['name'];
            $itm->FarmID = $row['farm_id'];
            $itm->FarmRoleID = $row['farm_roleid'];
            $itm->IsSSLEnabled = $row['is_ssl_enabled'];
            $itm->LastModifiedAt = $row['last_modified'];

            $response->ApacheVhostSet->Item[] = $itm;
        }

        return $response;
    }
}
