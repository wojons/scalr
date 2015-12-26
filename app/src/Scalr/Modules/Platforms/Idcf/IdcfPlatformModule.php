<?php

namespace Scalr\Modules\Platforms\Idcf;

use Scalr\Model\Entity\CloudCredentialsProperty;
use Scalr\Modules\Platforms\Cloudstack\CloudstackPlatformModule;

class IdcfPlatformModule extends CloudstackPlatformModule
{

    public function __construct()
    {
        parent::__construct(\SERVER_PLATFORMS::IDCF);
    }

    public function getLocations(\Scalr_Environment $environment = null) {
        return array(
            "jp-east-t1v"	=> "IDCF / jp-east-t1v",
            "jp-east-f2v"	=> "IDCF / jp-east-f2v",
        );
    }

    public function PutAccessData(\DBServer $DBServer, \Scalr_Messaging_Msg $message)
    {
        $put = false;
        $put |= $message instanceof \Scalr_Messaging_Msg_Rebundle;
        $put |= $message instanceof \Scalr_Messaging_Msg_BeforeHostUp;
        $put |= $message instanceof \Scalr_Messaging_Msg_HostInitResponse;
        $put |= $message instanceof \Scalr_Messaging_Msg_Mysql_PromoteToMaster;
        $put |= $message instanceof \Scalr_Messaging_Msg_Mysql_NewMasterUp;
        $put |= $message instanceof \Scalr_Messaging_Msg_Mysql_CreateDataBundle;
        $put |= $message instanceof \Scalr_Messaging_Msg_Mysql_CreateBackup;

        $put |= $message instanceof \Scalr_Messaging_Msg_DbMsr_PromoteToMaster;
        $put |= $message instanceof \Scalr_Messaging_Msg_DbMsr_CreateDataBundle;
        $put |= $message instanceof \Scalr_Messaging_Msg_DbMsr_CreateBackup;
        $put |= $message instanceof \Scalr_Messaging_Msg_DbMsr_NewMasterUp;

        if ($put) {
            $ccProps = $DBServer->GetEnvironmentObject()->cloudCredentials($DBServer->platform)->properties;

            $accessData = new \stdClass();
            $accessData->apiKey = $ccProps[CloudCredentialsProperty::CLOUDSTACK_API_KEY];
            $accessData->secretKey = $ccProps[CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY];

            $apiUrl = $ccProps[CloudCredentialsProperty::CLOUDSTACK_API_URL];
            if ($apiUrl == 'https://apis.i.noahcloud.jp/portal/client/api')
                $accessData->apiUrl = "https://api.noahcloud.jp/portal/client/api";
            else
                $accessData->apiUrl = $apiUrl;

            $message->platformAccessData = $accessData;
        }
    }
}
