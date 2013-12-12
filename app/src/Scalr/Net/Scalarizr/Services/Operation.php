<?php

class Scalr_Net_Scalarizr_Services_Operation extends Scalr_Net_Scalarizr_Client
{
    public function __construct(DBServer $dbServer, $port = 8010) {
        $this->namespace = "operation";
        parent::__construct($dbServer, $port);
    }

    public function getStatus($operationId)
    {
        $params = new stdClass();
        $params->operationId = $operationId;

        return $this->request("result", $params)->result;
    }
}