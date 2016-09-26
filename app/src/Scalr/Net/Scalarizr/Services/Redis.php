<?php

class Scalr_Net_Scalarizr_Services_Redis extends Scalr_Net_Scalarizr_Services_Dbmsr
{
    public function __construct(DBServer $dbServer, $port = 8010) {
        $this->namespace = "redis";
        parent::__construct($dbServer, $port);
    }
}