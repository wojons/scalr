<?php

namespace Scalr\LogCollector;

use DateTime;
use Scalr\Exception\LoggerException;
use Scalr\LogCollector\Writers\AbstractWriter;
use Scalr\LogCollector\Writers\Fluentd;
use Exception;

/**
 * AuditLogger
 *
 * @author Constantine Karnacvevych <c.karnacevych@scalr.com>
 */
abstract class AbstractLogger
{

    /**
     * A backend writer
     *
     * @var AbstractWriter
     */
    protected $writer;

    /**
     * Indicates whether logging is enabled
     *
     * @var boolean
     */
    protected $enabled = false;

    /**
     * Default tag to send
     *
     * @var string
     */
    protected $defaultTag;

    /**
     * Event subscribers
     *
     * @var array
     */
    protected $subscribers;

    /**
     * Constructor. Instantiates AbstractLogger, prepares backend
     *
     * @param   array   $config Logger configuration
     * @throws  LoggerException
     */
    public function __construct($config)
    {
        $this->enabled = $config["enabled"];

        if ($this->enabled === true) {
            $this->defaultTag = $config["tag"];
            $this->validateConfig($config);
            $this->setWriter($config);
        }

        $this->initializeSubscribers();
    }

    /**
     * Returns whether logger is enabled
     *
     * @return boolean true if logger enabled, else - false
     */
    public function isEnabled()
    {
        return $this->enabled === true;
    }

    /**
     * Set is enabled logger
     *
     * @param bool $isEnabled Whether logger is enabled
     * @return AbstractLogger
     */
    public function setIsEnabled($isEnabled = null)
    {
        $this->enabled = $isEnabled === true;

        return $this;
    }

    /**
     * Validates configuration options
     *
     * @param   array   $config Config passed from YAML
     * @throws  LoggerException
     */
    private function validateConfig(array $config)
    {
        if (!in_array($config["backend"], ["fluentd", "logstash"])) {
            throw new LoggerException(sprintf("Unknown Audit Logger backend %s", $config["backend"]));
        }

        if (!in_array($config["proto"], ["file", "php", "http", "tcp", "udp", "udg", "unix"])) {
            throw new LoggerException(sprintf("Audit Logger doesn't support %s protocol", $config["proto"]));
        }

        if (in_array($config["proto"], ["file", "unix", "udg"])) {
            if (strpos($config["path"], "/") !== 0 || strlen($config["path"]) < 5) {
                throw new LoggerException(sprintf("Invalid path specified (%s)", $config["path"]));
            }
        } elseif ($config["proto"] === "php") {
            if ($config["path"] != "output") {
                throw new LoggerException(sprintf("Invalid path specified (%s)", $config["path"]));
            }
        } else {
            if (strpos($config["path"], "/") === 0) {
                throw new LoggerException(sprintf("Invalid hostname specified (%s)", $config["path"]));
            }

            if ($config["port"] < 1 || $config["port"] > 65535) {
                throw new LoggerException(sprintf("Invalid port specified (%s)", $config["port"]));
            }
        }
    }

    /**
     * Set writer to AbstractLogger
     *
     * @example
     * <code>
     * $config = [
     *     "backend" => "fluentd",
     *     "proto"   => "udp",
     *     "path"    => "127.0.0.1",
     *     "port"    => 5160,
     *     "timeout  => 1,
     * ];
     * </code>
     * This will send logs to a socket at udp://127.0.0.1:5160
     *
     * @param   array   $config Accepted keys are: proto, path, port, and timeout
     * @return  AbstractLogger
     */
    protected function setWriter(array $config)
    {
        $class = substr(Fluentd::class, 0, -8) . "\\" . ucfirst($config["backend"]);

        $this->writer = new $class($config["proto"], $config["path"], $config["port"], $config["timeout"]);

        return $this;
    }

    /**
     * Gets current timestamp in common format.
     *
     * @param  int    $time   Unix timestamp
     * @return string Returns current timestamp in the server time zone.
     */
    public static function getTimestamp($time = null)
    {
        return date(DateTime::RFC3339, $time ?: time());
    }

    /**
     * Initializes Event subscribers
     *
     * The use of the subscribers is to transform object to array
     */
    protected function initializeSubscribers()
    {
        $this->subscribers = [];
    }

    /**
     * Prepares extra data to pass to a backend
     *
     * @return array Prepared extra data for logging
     */
    protected function getCommonData()
    {
        $data = ['timestamp' => static::getTimestamp()];

        return $data;
    }

    /**
     * Logs event to a specified backend
     *
     * @param  string  $event      Event tag
     * @param  mixed   $extra      optional Extra data to pass.
     * @param  mixed   $extra,...  optional
     * @return boolean Indicates whether operation was successful
     * @throws AuditLoggerException
     */
    public function log($event, ...$extra)
    {
        if (!$this->enabled) {
            return true;
        }

        if (!empty($extra)) {
            if (array_key_exists($event, $this->subscribers)) {
                $extra = $this->subscribers[$event](...$extra);
            } else {
                $extra = $extra[0];
            }
        } else {
            $extra = [];
        }

        $adjusted = [];

        foreach ($extra as $key => $val) {
            if (($pos = strpos($key, '.')) === 0) {
                //It will adjust data key with the event name when the key either does not contain
                //dot or starts with dot.
                $adjusted[$event . $key] = $val;
            } else {
                $adjusted[$key] = $val;
            }
        }

        $adjusted = array_merge($this->getCommonData(), $adjusted);

        $adjusted["tags"] = [$event];

        if (!empty($this->defaultTag)) {
            $adjusted["tags"][] = $this->defaultTag;
        }

        $data = [
            "tag"      => $this->defaultTag,
            "message"  => $event,
            "extra"    => $adjusted,
        ];

        try {
            $result = $this->writer->send($data);
        } catch (Exception $e) {
            \Scalr::logException(new Exception(sprintf("Logger couldn't log the record: %s", $e->getMessage()), $e->getCode(), $e));

            $result = false;
        }

        return $result;
    }
}
