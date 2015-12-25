<?php

abstract class AbstractServerEvent
{
    /**
     * Identifier of the event
     *
     * @var string
     */
    private $EventID;

    /**
     * Identifier of the farm
     *
     * @var int
     */
    private $FarmID;

    /**
     * Db farm object
     *
     * @var \DBFarm
     */
    public $DBFarm;
    
    /**
     * Db server object
     *
     * @var \DBServer
     */
    public $DBServer;

    public $msgExpected = 0,
        $msgCreated = 0,
        $msgSent = 0;
    
    public $scriptsCount = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->EventID = \Scalr::GenerateUID();
    }

    /**
     * Set FarmID for Event
     * @param integer $farm_id
     * @throws Exception
     * @return void
     */
    public function SetFarmID($farm_id)
    {
        if ($this->FarmID === null) {
            $this->FarmID = $farm_id;
            if ($farm_id) {
                $this->DBFarm = DBFarm::LoadByID($farm_id);
            }
        } else
            throw new Exception("FarmID already set for this event");
    }

    public static function GetScriptingVars()
    {
        return array();
    }

    /**
     * Returns Event FarmID
     * @return integer $farm_id
     */
    public function GetFarmID()
    {
        return $this->FarmID;
    }

    /**
     * Returns event unique ID
     * @return string
     */
    public function GetEventID()
    {
        return $this->EventID;
    }

    /**
     * Returns event name
     *
     * @return string
     */
    public function GetName()
    {
        return str_replace("Event", "", get_class($this));
    }

    /**
     * Returns text details
     *
     * @return string
     */
    public function getTextDetails()
    {
        return $this->GetName();
    }
}
