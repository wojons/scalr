<?php

class Scalr_Messaging_Msg
{

    public $messageId;

    protected $messageName;

    public $meta = array();

    public $handlers = array();

    public $behaviour,
        $roleName,
        $localIp,
        $remoteIp,
        $serverIndex,
        $serverId,
        $cloudLocation,
        $farmRoleId;


    public function __construct ()
    {
        $this->messageId = Scalr::GenerateUID();
        $this->meta[Scalr_Messaging_MsgMeta::SCALR_VERSION] = SCALR_VERSION;
    }

    public function setServerMetaData(DBServer $dbServer)
    {
        try {
            $this->behaviour = $dbServer->GetFarmRoleObject()->GetRoleObject()->getBehaviors();
            $this->roleName = $dbServer->GetFarmRoleObject()->GetRoleObject()->name;
            $this->farmRoleAlias = $dbServer->GetFarmRoleObject()->Alias;

            if (empty($this->farmRoleAlias))
                $this->farmRoleAlias = $this->roleName;
        } catch (Exception $e) {
        }

        $this->localIp = $dbServer->localIp;
        $this->remoteIp = $dbServer->remoteIp;
        $this->serverIndex = $dbServer->index;
        $this->serverId = $dbServer->serverId;
        $this->cloudLocation = $dbServer->GetCloudLocation();
        $this->farmRoleId = $dbServer->farmRoleId;
    }

    public function setGlobalVariables(DBServer $dbServer, $includeSystem = false, Event $event = null)
    {
        $this->globalVariables = Scalr_Scripting_GlobalVariables::listServerGlobalVariables(
            $dbServer,
            $includeSystem,
            $event
        );
    }

    public function setName($name)
    {
        if ($this->messageName === null)
            $this->messageName = $name;
    }

    public function getName()
    {
        if ($this->messageName === null) {
            $this->messageName = substr(get_class($this), strlen(__CLASS__) + 1);
        }
        return $this->messageName;
    }

    public function getTimestamp()
    {
        return strtotime($this->meta[Scalr_Messaging_MsgMeta::TIMESTAMP]);
    }

    public function getServerId()
    {
        return $this->meta[Scalr_Messaging_MsgMeta::SERVER_ID];
    }

    public function setServerId($serverId)
    {
        $this->meta[Scalr_Messaging_MsgMeta::SERVER_ID] = $serverId;
    }

    public static function getClassForName($name)
    {
        if (class_exists(__CLASS__ . "_" . $name))
            return __CLASS__ . "_" . $name;
        else
            return __CLASS__;
    }
}