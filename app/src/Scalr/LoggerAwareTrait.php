<?php
namespace Scalr;

use Scalr\Logger;

/**
 * LoggerAwareTrait
 *
 * It implements logger interface
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0.1 (13.10.2014)
 */
trait LoggerAwareTrait
{
    /**
     * Logger instance
     *
     * @var \Scalr\Logger
     */
    private $logger;

    /**
     * Sets a logger
     *
     * @param    Logger     $logger  A logger instance
     */
    public function setLogger(Logger $logger = null)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Gets  a logger
     *
     * @return \Scalr\Logger  Returns a logger insance
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Logs a messsage
     *
     * @param    int       $level    The log level
     * @param    string    $message  Format string
     * @param    string    $args     optional An arguments
     * @param    string    $args,... optional Number of additional arguments to output
     */
    public function log($level, $message, $args = null)
    {
        $pars = func_get_args();

        if ($this->logger) {
            call_user_func_array([$this->logger, 'log'], $pars);
        } else {
            //adding level to format string
            $pars[1] = $level . ' - ' . $message . "\n";

            //removing level from arguments to call printf
            array_shift($pars);

            call_user_func_array('printf', $pars);
        }
    }
}