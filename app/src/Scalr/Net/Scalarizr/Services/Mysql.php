<?php

class Scalr_Net_Scalarizr_Services_Mysql extends Scalr_Net_Scalarizr_Services_Dbmsr
{
    public function __construct(DBServer $dbServer, $port = 8010) {
        $this->namespace = "mysql";
        parent::__construct($dbServer, $port);
    }

    public function createBackup($backup, $platformAccessData)
    {
        $params = new stdClass();
        $params->backup = $backup;
        $params->_platform_access_data = $platformAccessData;
        $params->async = true;

        return $this->request("create_backup", $params)->result;
    }
}