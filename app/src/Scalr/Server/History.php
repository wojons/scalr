<?php

namespace Scalr\Server;

use Exception;
use ReflectionClass;
use ReflectionProperty;

/**
 * @deprecated
 *
 * Server History Entity class
 *
 * @since    4.5.0 (28.10.2013)
 */
class History
{

    // All public properties are the fields in the database table.

    public $id;

    public $clientId;

    public $serverId;

    public $cloudServerId;

    public $cloudLocation;

    public $dtlaunched;

    public $dtterminated;

    public $launchReason;

    public $launchReasonId;

    public $terminateReason;

    public $terminateReasonId;

    public $platform;

    public $type;

    public $envId;

    public $farmId;

    public $farmRoleid;

    public $serverIndex;

    public $scuUsed = .0;

    public $scuReported = .0;

    public $scuUpdated = 0;

    public $scuCollecting = 0;

    /**
     * DB Instance
     * @var \ADODB_mysqli
     */
    private $db;

    /**
     * Reflection
     * @var array;
     */
    private static $_fields;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = \Scalr::getDb();
    }

    /**
     * Marks server as launched.
     *
     * @param   string    $reason  The reason
     * @param   integer   $reasonId
     */
    public function markAsLaunched($reason, $reasonId)
    {
        $this->launchReason = $reason;
        $this->launchReasonId = $reasonId;
        $this->dtlaunched = date("Y-m-d H:i:s");
        $this->save();
    }

    /**
     * Marks server as terminated
     *
     * @param   string      $reason            The reason
     * @param   integer     $reasonId
     */
    public function markAsTerminated($reason, $reasonId)
    {
        $this->terminateReason = $reason;
        $this->terminateReasonId = $reasonId;
        $this->save();
    }

    /**
     * Set the date when server is said to have been terminated in the cloud.
     */
    public function setTerminated()
    {
        if (empty($this->dtterminated)) {
            $this->dtterminated = date('Y-m-d H:i:s');
            $this->save();
        }
    }

    /**
     * Gets server history object
     *
     * @param   string     $serverId  The identifier of the server
     * @return  History
     * @throws  \Scalr\Exception\ScalrException
     */
    public static function loadByServerId($serverId)
    {
        $db = \Scalr::getDb();
        $row = $db->GetRow("SELECT * FROM `servers_history` WHERE server_id = ? LIMIT 1", array($serverId));
        if (empty($row)) {
            throw new \Scalr\Exception\ScalrException(sprintf('Could not find server history by server identifier "%s"', $serverId));
        }
        $history = new self;
        foreach (self::_getFields() as $field) {
            $dbcol = \Scalr::decamelize($field);
            $history->$field = isset($row[$dbcol]) ? $row[$dbcol] : null;
        }

        return $history;
    }

    /**
     * Get fields list
     *
     * @return array Returns the list of the fields
     */
    private static function _getFields()
    {
        if (self::$_fields === null) {
            self::$_fields = array();
            $ref = new ReflectionClass(__CLASS__);
            foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $refProp) {
                /* @var $refProp \ReflectionProperty */
                self::$_fields[] = $refProp->getName();
            }
        }
        return self::$_fields;
    }

    /**
     * Saves an entity to database
     *
     * @return \Scalr\Server\History
     * @throws Exception
     */
    public function save()
    {
        $stmt = array();
        $bind = array();

        $idKey = 'id';
        $idValue = array();

        $cols = array();
        foreach ($this->_getFields() as $field) {
            $cols[$field] = $this->$field;
        }

        if (array_key_exists($idKey, $cols)) {
            if ($cols[$idKey]) {
                $idValue[] = $cols[$idKey];
            }
            unset($cols[$idKey]);
        }

        foreach ($cols as $field => $value) {
            $stmt[] = "`" . \Scalr::decamelize($field) . "` = ?";
            $bind[] = $value;
        }

        try {
            $stmt = (empty($idValue) ? "INSERT" : "UPDATE") . " `servers_history` SET " . (join(", ", $stmt))
                 .  (!empty($idValue) ? " WHERE `" . \Scalr::decamelize($idKey) . "` = ?" : "");

            $this->db->Execute($stmt, array_merge($bind, $idValue));

            if (empty($idValue)) {
                $this->$idKey = $this->db->Insert_ID();
            }
        } catch (Exception $e) {
            throw new Exception(sprintf("Cannot save server history record. Error: %s", $e->getMessage()), $e->getCode());
        }

        return $this;
    }
}
