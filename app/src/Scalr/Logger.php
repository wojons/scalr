<?php

namespace Scalr;

use FarmLogMessage;

/**
 * Logger
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.0 (19.09.2014)
 */
class Logger
{
    /**
     * Helps to convert a level into integer representation for writing into DB
     *
     * @var array
     */
    protected static $severities = [
        "DEBUG" => 1,
        "INFO"  => 2,
        "WARN"  => 3,
        "ERROR" => 4,
        "FATAL" => 5
    ];

    //Log level
    const LEVEL_OFF       = 2147483647;
    //Service message
    const LEVEL_SERVICE   = 2000000000;

    const LEVEL_FATAL     = 50000;
    const LEVEL_ERROR     = 40000;
    const LEVEL_WARN      = 30000;
    const LEVEL_INFO      = 20000;
    const LEVEL_DEBUG     = 10000;

    //0MQ MDP API debug
    const LEVEL_ZMQDEBUG  = 8000;

    const LEVEL_ALL       = -2147483647;

    /**
     * Retport level
     *
     * @var   int
     */
    protected $level;

    /**
     * Category name
     *
     * @var   string
     */
    protected $name;

    /**
     * Date format
     *
     * Nov 13 06:28:01 -06:00
     *
     * @var string
     */
    protected $dateFormat = 'M d H:i:s P';

    /**
     * Log level names
     *
     * @var array
     */
    protected static $logLevelName = [
        self::LEVEL_FATAL    => 'FATAL',
        self::LEVEL_ERROR    => 'ERROR',
        self::LEVEL_WARN     => 'WARN',
        self::LEVEL_INFO     => 'INFO',
        self::LEVEL_DEBUG    => 'DEBUG',
        self::LEVEL_ZMQDEBUG => 'ZMQDEBUG',
        self::LEVEL_SERVICE  => 'SERVICE',
    ];

    /**
     * Constructor
     *
     * @param   string   $name  optional The name of the category
     */
    public function __construct($name = '')
    {
        $this->level = $this::LEVEL_ALL;
        $this->name = $name;
    }

    /**
     * Gets the name of a logger category
     *
     * @return   string  Returns the name of a logger category
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the name of a logger category
     *
     * @param   string    $name  The name of the category or class
     * @return  Logger
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets log level
     *
     * @return   int  Returns log level
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * Sets log level
     *
     * @param   int|string   $logLevel  A log level also accepts (FATAL, ERROR, WARN, INFO, DEBUG)
     * @return  Logger
     * @throws  \InvalidArgumentException
     */
    public function setLevel($logLevel)
    {
        if (is_int($logLevel)) {
           $this->level = $logLevel;
        } else {
            $const = 'static::LEVEL_' . strtoupper($logLevel);

            if (!defined($const)) {
               throw new \InvalidArgumentException(sprintf("Unknown log level: %s", strip_tags($logLevel)));
            }

            $this->level = constant($const);
        }

        return $this;
    }

    /**
     * Gets the format of the date
     *
     * @return   string  Returns the format of the date
     */
    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    /**
     * Sets the format of the date
     *
     * @param   string    $dateFormat  The format of the date
     * @return  Logger
     */
    public function setDateFormat($dateFormat)
    {
        $this->dateFormat = $dateFormat;

        return $this;
    }

    /**
     * Raw log message
     *
     * @param   int|string $level    The log level
     * @param   string     $message  The message that accepts format specification
     * @param   string     $args     optional An arguments
     * @param   string     $args,... optional Number of additional arguments to output
     */
    public function log($level, $message, $args = null)
    {
        if (!is_numeric($level) && defined('static::LEVEL_' . $level)) {
            //Translates string log level to integer
            $level = constant('static::LEVEL_' . $level);
        }

        if ($message instanceof FarmLogMessage) {
            $time = time();
            $tm = date('YmdH');
            $hash = md5(":{$message->Message}:{$message->FarmID}:{$this->name}:{$tm}", true);

            $logLevelName = self::$logLevelName[$level];

            try {
                \Scalr::getDb()->Execute("
                    INSERT INTO logentries SET
                        `id` = ?,
                        `serverid` = ?,
                        `message` = ?,
                        `severity` = ?,
                        `time` = ?,
                        `source` = ?,
                        `farmid` = ?,
                        `env_id` = ?,
                        `farm_role_id` = ?
                    ON DUPLICATE KEY UPDATE cnt = cnt + 1, `time` = ?", [
                        $hash,
                        $message->ServerID,
                        $message->Message,
                        self::$severities[$logLevelName],
                        $time,
                        $this->name,
                        $message->FarmID,
                        $message->envId,
                        $message->farmRoleId,
                        $time
                    ]
                );
            } catch (\Exception $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
            }

            \Scalr::getContainer()->userlogger->log('user.log', $message, $logLevelName);

            $message = "[FarmID: {$message->FarmID}] {$message->Message}";
        }

        if (stripos(PHP_SAPI, "cli") === false) {
            return;
        }

        if ($level >= $this->level) {
            $args = array_slice(func_get_args(), 2);

            fwrite(STDOUT, sprintf("%s - %s@%d - %s - %s\n",
                date($this->dateFormat),
                (!empty($this->name) ? $this->name : ''),
                posix_getpid(),
                (isset(static::$logLevelName[$level]) ? static::$logLevelName[$level] : $level),
                (empty($args) ? $message : vsprintf($message, $args))
            ));
        }
    }

    /**
     * Log a message with the DEBUG level.
     *
     * @param   string    $message  The message that accepts format
     * @param   string    $args     optional An arguments
     * @param   string    $args,... optional Number of additional arguments to output
     */
    public function debug($message, $args = null)
    {
        $args = func_get_args();
        array_unshift($args, self::LEVEL_DEBUG);
        call_user_func_array([$this, 'log'], $args);
    }

    /**
     * Log a message with the INFO level.
     *
     * @param   string    $message  The message that accepts format
     * @param   string    $args     optional An arguments
     * @param   string    $args,... optional Number of additional arguments to output
     */
    public function info($message, $args = null)
    {
        $args = func_get_args();
        array_unshift($args, self::LEVEL_INFO);
        call_user_func_array([$this, 'log'], $args);
    }

    /**
     * Log a message with the WARN level.
     *
     * @param   string    $message  The message that accepts format
     * @param   string    $args     optional An arguments
     * @param   string    $args,... optional Number of additional arguments to output
     */
    public function warn($message, $args = null)
    {
        $args = func_get_args();
        array_unshift($args, self::LEVEL_WARN);
        call_user_func_array([$this, 'log'], $args);
    }

    /**
     * Log a message with the ERROR level.
     *
     * @param   string    $message  The message that accepts format
     * @param   string    $args     optional An arguments
     * @param   string    $args,... optional Number of additional arguments to output
     */
    public function error($message, $args = null)
    {
        $args = func_get_args();
        array_unshift($args, self::LEVEL_ERROR);
        call_user_func_array([$this, 'log'], $args);
    }

    /**
     * Log a message with the FATAL level.
     *
     * @param   string    $message  The message that accepts format
     * @param   string    $args     optional An arguments
     * @param   string    $args,... optional Number of additional arguments to output
     */
    public function fatal($message, $args = null)
    {
        $args = func_get_args();
        array_unshift($args, self::LEVEL_FATAL);
        call_user_func_array([$this, 'log'], $args);
    }
}
