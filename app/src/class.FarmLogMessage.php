<?php

class FarmLogMessage
{
    /**
     * Identifier of the farm
     *
     * @var int
     */
    public $FarmID;

    /**
     * Log message
     *
     * @var string
     */
    public $Message;

    /**
     * Identifier of the server
     *
     * @var string
     */
    public $ServerID;

    /**
     * Identifier of the environment
     *
     * @var string
     */
    public $envId;

    /**
     * Identifier of the farm role
     *
     * @var string
     */
    public $farmRoleId;

    /**
     * Constructor. Instantiates FarmLogMessage
     *
     * @param DBServer|int  $param1     DBServer object or farm id
     * @param string        $message    Message for writing in log
     * @param mixed         $extra      optional Extra data for passing in FarmLogMessage::constructBySeparateParams().
     *                                  Used with farm id in first param
     * @param mixed         $extra,...  optional
     *
     * @return void
     */
    function __construct($param1, $message, ...$extra)
    {
        if ($param1 instanceof DBServer) {
            $this->constructByDBServer($param1, $message);
        } else {
            $this->constructBySeparateParams($param1, $message, ...$extra);
        }
    }

    /**
     * Constructor. Instantiates FarmLogMessage
     *
     * @param int       $farmId     Identifier of the farm
     * @param string    $message    Message for writing in log
     * @param int       $serverid   optional Identifier of the server
     * @param int       $envId      optional Identifier of the environment
     * @param int       $farmRoleId optional Identifier of the farm role
     *
     * @return void
     */
    protected function constructBySeparateParams($farmId, $message, $serverid = null, $envId = null, $farmRoleId = null)
    {
        $this->FarmID = $farmId;
        $this->Message = $message;
        $this->ServerID = $serverid;
        $this->envId = $envId;
        $this->farmRoleId = $farmRoleId;
    }

    /**
     * Constructor by separate params
     *
     * @param DBServer  $dbServer   DBServer object for retrieving log data
     * @param string    $message    Message for writing in log
     *
     * @return void
     */
    protected function constructByDBServer(DBServer $dbServer, $message)
    {
        $this->FarmID = !empty($dbServer->farmId) ? $dbServer->farmId : null;
        $this->ServerID = !empty($dbServer->serverId) ? $dbServer->serverId : null;
        $this->envId = !empty($dbServer->envId) ? $dbServer->envId : null;
        $this->farmRoleId = !empty($dbServer->farmRoleId) ? $dbServer->farmRoleId : null;
        $this->Message = $message;
    }
}
