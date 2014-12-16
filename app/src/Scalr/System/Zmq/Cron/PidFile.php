<?php
namespace Scalr\System\Zmq\Cron;

use Scalr\LoggerTrait;

/**
 * PidFile handler
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0 (22.09.2014)
 */
class PidFile
{
    use LoggerTrait;

    /**
     * Path to the pid file
     *
     * @var string
     */
    private $file;

    /**
     * Command to check
     *
     * @var string
     */
    private $command;

    /**
     * PID associated with process
     *
     * @var int
     */
    private $pid;

    /**
     * The level of the message when pid does exist
     *
     * @var string
     */
    public $pidExistLevel = 'ERROR';

    /**
     * Constructor
     *
     * @param   string    $file    Path to PID file
     * @param   string    $command Command to check process
     */
    public function __construct($file, $command)
    {
        $this->file = $file;
        $this->command = $command;
    }

    /**
     * Checks pid
     *
     * @return  int|boolean  Returns PID if it's found, or false otherwise
     */
    public function check()
    {
        if (file_exists($this->file)) {
            if (!is_readable($this->file)) {
                $this->log("ERROR", "Could not open pid file '%s' for reading.", $this->file);
                exit;
            } if (!is_writable($this->file)) {
                $this->log("ERROR", "Could not open pid file '%s' for writing.", $this->file);
                exit;
            } else {
                //Reading from pid file
                $this->pid = intval(trim(file_get_contents($this->file)));

                //Trying to find that one
                $op = [];
                exec('ps -p ' . $this->pid . ' -o pid,command | egrep "' . preg_quote($this->command, '"') . '"', $op);

                $found = false;
                if (!empty($op)) {
                    foreach ($op as $str) {
                        $pid = substr(ltrim($str), 0, strpos(ltrim($str), ' '));

                        if ($this->pid == $pid) {
                            $found = $pid;
                            break;
                        }
                    }
                }

                if (!$found) {
                    //Trying to recover obsolete pid
                    $this->log("WARN", "Removing obsolete pid file '%s' as process %d does not exist", $this->file, $this->pid);
                    $this->remove();
                }

                return $found;
            }
        }

        return false;
    }

    /**
     * Creates pid file
     *
     * It performs pid file check itself
     */
    public function create()
    {
        if ($this->check()) {
            //We are using warning here to eliminate messages flood in the service, nevertheless it's actually error.
            $this->log($this->pidExistLevel, "Cannot start service, another one is already running! pid:%d, file:%s", $this->pid, $this->file);
            exit;
        }

        //Creates pid file
        $this->pid = posix_getpid();

        $res = file_put_contents($this->file, $this->pid);

        if ($res === false) {
            $this->log("ERROR", "Cannot create pid file: %s", $this->file);
            exit;
        }

        @chmod($this->file, 0666);
    }

    /**
     * Removes pid file
     */
    public function remove()
    {
        if (file_exists($this->file)) {
            if (is_writable($this->file)) {
                unlink($this->file);
            } else {
                $this->log("ERROR", "Could not remove pid file '%s'. Insufficient permissions.", $this->file);
            }
        }
    }
}