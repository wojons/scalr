<?php

class FarmLaunchedEvent extends Event
{
    public $MarkInstancesAsActive;
    public $userId;

    public function __construct($MarkInstancesAsActive, $userId = null)
    {
        parent::__construct();

        $this->MarkInstancesAsActive = $MarkInstancesAsActive;
        $this->userId = $userId;
    }

    public function getTextDetails()
    {
        return "Farm has been launched";
    }
}
