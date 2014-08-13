<?php

class Scalr_Net_Scalarizr_Services_Image extends Scalr_Net_Scalarizr_Client
{
    public function __construct(DBServer $dbServer, $port = 8010) {
        $this->namespace = "image";
        parent::__construct($dbServer, $port);
    }

    public function create($name, $platformAccessData, $async = true) {
        $params = new stdClass();
        $params->name = $name;
        $params->async = $async;
        $params->_platform_access_data = $platformAccessData;

        return $this->request("create", $params)->result;
    }

    public function prepare($platformAccessData, $async = false) {
        $params = new stdClass();
        $params->async = $async;
        $params->_platform_access_data = $platformAccessData;

        return $this->request("prepare", $params)->result;
    }

    public function finalize($platformAccessData, $async = false) {
        $params = new stdClass();
        $params->async = $async;
        $params->_platform_access_data = $platformAccessData;

        return $this->request("finalize", $params)->result;
    }

    public function snapshot($name, $platformAccessData, $async = true) {
        $params = new stdClass();
        $params->name = $name;
        $params->async = $async;
        $params->_platform_access_data = $platformAccessData;

        return $this->request("snapshot", $params)->result;
    }
}