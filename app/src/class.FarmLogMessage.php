<?php

class FarmLogMessage
{
    public $FarmID;
    public $Message;
    public $ServerID;

    function __construct($farmid, $message, $serverid = null)
    {
        $this->FarmID = $farmid;
        $this->Message = $message;
        $this->ServerID = $serverid;
    }
}
