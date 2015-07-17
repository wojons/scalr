<?php

class LoggerAppenderScalr extends LoggerAppender
{

    /**
     * Create the log table if it does not exists (optional).
     * @var boolean
     */
    var $createTable = true;

    /**
     * The type of database to connect to
     * @var string
     */
    var $type;

    /**
     * Database user name
     * @var string
     */
    var $user;

    /**
     * Database password
     * @var string
     */
    var $password;

    /**
     * Database host to connect to
     * @var string
     */
    var $host;

    /**
     * Name of the database to connect to
     * @var string
     */
    var $database;

    /**
     * A {@link LoggerPatternLayout} string used to format a valid insert query (mandatory).
     * @var string
     */
    var $sql;

    /**
     * Table name to write events. Used only if {@link $createTable} is true.
     * @var string
     */
    var $table;

    /**
     * @var object Adodb instance
     * @access private
     */
    var $db = null;

    /**
     * @var boolean used to check if all conditions to append are true
     * @access private
     */
    var $canAppend = true;

    /**
     * @access private
     */
    var $requiresLayout = false;

    /**
     * Constructor.
     *
     * @param string $name appender name
     */
    function LoggerAppenderDb($name)
    {
        $this->LoggerAppenderSkeleton($name);
    }

    /**
     * Setup db connection.
     * Based on defined options, this method connects to db defined in {@link $dsn}
     * and creates a {@link $table} table if {@link $createTable} is true.
     * @return boolean true if all ok.
     */
    function activateOptions()
    {
        $this->db = \Scalr::getDb();

        $this->layout = LoggerReflectionUtils::createObject('LoggerLayoutPattern');
        $this->layout->setConversionPattern($this->getSql());

        $this->canAppend = true;
    }

    function append(LoggerLoggingEvent $event)
    {
        if ($this->canAppend) {
            try {
                // Reopen new mysql connection (need for php threads)
                $this->activateOptions();

                if ($event->message instanceof FarmLogMessage) {
                    $severity = $this->SeverityToInt($event->getLevel()->toString());
                    $message = $event->message->Message;
                    $tm = date('YmdH');
                    $hash = md5(":{$message}:{$event->message->FarmID}:{$event->getLoggerName()}:{$tm}", true);
                    $serverId = $event->message->ServerID;

                    $query = "INSERT DELAYED INTO logentries SET
                        `id` = ?,
                        `serverid`	= ?,
                        `message`	= ?,
                        `severity`	= ?,
                        `time`		= ?,
                        `source` 	= ?,
                        `farmid` 	= ?
                        ON DUPLICATE KEY UPDATE cnt = cnt + 1, `time` = ?
                    ";

                    $this->db->Execute($query, array(
                        $hash,
                        $serverId,
                        $message,
                        $severity,
                        time(),
                        $event->getLoggerName(),
                        $event->message->FarmID,
                        time()
                    ));

                    $event->message = "[FarmID: {$event->message->FarmID}] {$event->message->Message}";

                    return;
                } else {
                    // No longer log stuff in syslog table
                    return;
                }
            }
            catch(Exception $e) {}
        }
    }

    function SeverityToInt($severity)
    {
        $severities = array("DEBUG" => 1, "INFO" => 2, "WARN" => 3, "ERROR" => 4, "FATAL" => 5);

        return $severities[$severity];
    }

    function close()
    {
        if ($this->db !== null)
            $this->db->Close();
        $this->closed = true;
    }

    /**
     * @return boolean
     */
    function getCreateTable()
    {
        return $this->createTable;
    }

    /**
     * @return string the sql pattern string
     */
    function getSql()
    {
        return $this->sql;
    }

    /**
     * @return string the table name to create
     */
    function getTable()
    {
        return $this->table;
    }

    /**
     * @return string the database to connect to
     */
    function getDatabase() {
        return $this->database;
    }

    /**
     * @return string the database to connect to
     */
    function getHost() {
        return $this->host;
    }

    /**
     * @return string the user to connect with
     */
    function getUser() {
        return $this->user;
    }

    /**
     * @return string the password to connect with
     */
    function getPassword() {
        return $this->password;
    }

    /**
     * @return string the type of database to connect to
     */
    function getType() {
        return $this->type;
    }

    function setCreateTable($flag)
    {
        $this->createTable = LoggerOptionConverter::toBoolean($flag, true);
    }

    function setType($newType)
    {
        $this->type = $newType;
    }

    function setDatabase($newDatabase)
    {
        $this->database = $newDatabase;
    }

    function setHost($newHost)
    {
        $this->host = $newHost;
    }

    function setUser($newUser)
    {
        $this->user = $newUser;
    }

    function setPassword($newPassword)
    {
        $this->password = $newPassword;
    }

    function setSql($sql)
    {
        $this->sql = $sql;
    }

    function setTable($table)
    {
        $this->table = $table;
    }

}
?>