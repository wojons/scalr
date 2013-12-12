<?php

namespace Scalr\Upgrade;

use Scalr\Upgrade\Exception\ConsoleException;

/**
 * Console
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (11.10.2013)
 */
class Console
{
    public $timeformat = 'i:s';
    public $keeplog = true;

    /**
     * The log
     *
     * @var array()
     */
    private $log;

    /**
     * Console
     */
    public function __construct()
    {
        $this->cleanup();
    }

    /**
     * Clears log
     */
    public function cleanup()
    {
        $this->log = array();
    }

    /**
     * Gets full log as text
     *
     * @return  string  Returns full log as text
     */
    public function getLog()
    {
        return join("\n", $this->log);
    }

    /**
     * Gets formatted message
     *
     * @param   string    $arguments  The list of arguments
     * @return  string    Returns formatted message without colors
     */
    protected function getMessage($arguments)
    {
        $time = !empty($this->timeformat) ? gmdate($this->timeformat) . ' - ' : '';
        return sprintf("%s%s", $time, call_user_func_array('sprintf', $arguments));
    }

    /**
     * Register message in the log end returns its content without colors
     *
     * @param   string     $arguments  The list of the format and arguments
     * @return  string     Returns message without colours
     */
    protected function registerMessage($arguments)
    {
        $message = $this->getMessage($arguments);
        if ($this->keeplog) {
            $this->log[] = $message;
        }

        return $message;
    }

    /**
     * Outputs the message to stdout
     *
     * @param   string     $fmt      A format string
     * @param   string     $args     optional An arguments
     * @param   string     $args,... optional Number of additional arguments to output
     */
    public function out($fmt, $args = null)
    {
        printf("%s\n", $this->registerMessage(func_get_args()));
    }

    /**
     * Outputs the error message to stdout
     *
     * @param   string     $fmt      A format string
     * @param   string     $args     optional An arguments
     * @param   string     $args,... optional Number of additional arguments to output
     */
    public function error($fmt, $args = null)
    {
        printf("\033[31m%s\033[0m\n", $this->registerMessage(func_get_args()));
    }

    /**
     * Outputs the notice message to stdout
     *
     * @param   string     $fmt      A format string
     * @param   string     $args     optional An arguments
     * @param   string     $args,... optional Number of additional arguments to output
     */
    public function notice($fmt, $args = null)
    {
        printf("\033[1;30m%s\033[0m\n", $this->registerMessage(func_get_args()));
    }

    /**
     * Outputs the warning message to stdout
     *
     * @param   string     $fmt      A format string
     * @param   string     $args     optional An arguments
     * @param   string     $args,... optional Number of additional arguments to output
     */
    public function warning($fmt, $args = null)
    {
        printf("\033[0;33m%s\033[0m\n", $this->registerMessage(func_get_args()));
    }

    /**
     * Outputs the success message to stdout
     *
     * @param   string     $fmt      A format string
     * @param   string     $args     optional An arguments
     * @param   string     $args,... optional Number of additional arguments to output
     */
    public function success($fmt, $args = null)
    {
        printf("\033[32m%s\033[0m\n", $this->registerMessage(func_get_args()));
    }

    /**
     * Runs console command
     *
     * @param   string    $cmd  Console command
     * @return  array     Returns output
     */
    public function run($cmd)
    {
        exec($cmd, $output, $ret);
        if ($ret != 0) {
            $message = sprintf('Command `%s` failed! %s', $cmd, join("\n", $output));
            $this->error($message);
            throw new ConsoleException($message);
        }
        return $output;
    }

    /**
     * Inputs string from STDIN
     *
     * @param   string     $message optional Information message what do it want to be entered
     * @param   bool       $hide    optional If true this output will be hidden (i.e. For passwords)
     * @return  string
     */
    public function input($message = '', $hide = false)
    {
        if ($message != '' || $hide) {
            //We should read password from prompt
            fwrite(STDOUT, (isset($message) ? $message : 'Please tap Enter key') . ": ");
        }
        if ($hide) {
            //Hiding the entered password
            system('stty -echo');
        }

        $ret = fgets(STDIN);

        if ($hide) {
            system('stty echo');
            print "\n";
        }

        return rtrim($ret, "\n");
    }

    /**
     * Confirmation dialog
     *
     * @param   string     $message The confirmation message
     * @param   bool       $default optional Default value: true - yes or false - no
     * @return  bool       Returns true if confirmed
     */
    public function confirm($message, $default = false)
    {
        $y = strtolower(trim($this->input($message . " (Default: " . ($default ? "yes" : "no") . ") ")));
        switch (true) {
            case ($y == 'y') :
            case ($y == 'yes') :
                return true;
                break;

            case ($y == 'n') :
            case ($y == 'no') :
                return false;
                break;

            default:
                return $default;
        }
    }
}