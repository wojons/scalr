<?php

namespace Scalr\Util;

use DateTime;
use Scalr\Exception\LoggerException;
use Scalr\Util\Logger\AbstractWriter;
use Scalr\Util\Logger\Writers\Fluentd;

/**
 * AuditLogger
 *
 * @author Constantine Karnacvevych <c.karnacevych@scalr.com>
 */
class Logger
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
     * Cached user information
     *
     * @var array
     */
    protected $cached = [];

    /**
     * Constructor. Instantiates Logger, prepares backend
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
     * Set writer to AuditLogger
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
     * @return  Logger
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
}
