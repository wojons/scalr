<?php
namespace Scalr\System\Zmq\Cron;

use DateTime;
use Exception;
use Scalr\LoggerAwareTrait;
use Scalr\Model\Entity\ScalrService;
use Scalr\System\Zmq\Mdp\Client;
use Scalr\System\Zmq\Zmsg;

/**
 * Crontab launcher
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0.1 (15.09.2014)
 */
class Launcher
{
    use LoggerAwareTrait;

    /**
     * Start time
     *
     * @var DateTime
     */
    private $start;

    /**
     * Constructor
     *
     * @param   DateTime $start  Start time in system timezone
     */
    public function __construct(DateTime $start)
    {
        $this->start = clone $start;
    }

    /**
     * Gets the list of the tasks which are due to run
     *
     * @return \Scalr\System\Zmq\Cron\ScheduledServiceIterator
     */
    public function getScheduled()
    {
        return new ScheduledServiceIterator($this->start);
    }

    /**
     * Gets the list of all tasks
     *
     * @return \Scalr\System\Zmq\Cron\ServiceIterator
     */
    public function getAllTasks()
    {
        return new ServiceIterator();
    }

    /**
     * Launches tasks are due to run
     *
     * @return  int  Returns the number of the launched tasks
     */
    public function launch()
    {
        $curTime = new DateTime('now');
        if ($curTime->format('i') % 5 == 1) {
            $services = [];

            foreach (ScalrService::find() as $scalrService) {
                $services[$scalrService->name] = $scalrService;
            }

            foreach ($this->getAllTasks() as $task) {
                $config = $task->config();

                if (!array_key_exists($task->getName(), $services)) {
                    $task->getScalrService()->update(['state' => $config->enabled ? ScalrService::STATE_SCHEDULED : ScalrService::STATE_DISABLED]);
                } elseif (!$config->enabled && $services['state'] != ScalrService::STATE_DISABLED) {
                    $task->getScalrService()->update(['state' => ScalrService::STATE_DISABLED]);
                }
            }
        }

        $count = 0;
        //Gets all scheduled task at this second
        foreach ($this->getScheduled() as $task) {
            /* @var $task \Scalr\System\Zmq\Cron\TaskInterface */
            $this->log("DEBUG", "Launching %s", $task->getName());

            $task->launch();

            $count++;
        }

        return $count;
    }

    /**
     * Makes sure the broker is running.
     * If it isn't running method will start it.
     *
     * @return int  Returns non false if broker is running
     */
    public static function ensureBrokerRunning()
    {
        $client = (new Client(\Scalr::config('scalr.crontab.sockets.broker')))
            ->setTimeout(100)
            ->setRetries(1)
            ->setLogger(\Scalr::getContainer()->logger('Mdp\\Client')->setLevel(\Scalr::config('scalr.crontab.log_level')))
            ->connect()
        ;

        $mmiReq = new Zmsg();
        $mmiReq->push("system.healthcheck");
        $mmiRep = $client->send("mmi.service", $mmiReq);

        if ($mmiRep) {
            $ok = $mmiRep->pop();
        } else {
            $ok = false;

            //Make sure another broker process isn't hanging
            self::terminateBroker();

            //Broker has to be started in the separate process
            $op = [];

            $logFile = \Scalr::config('scalr.crontab.log');

            exec(self::getStartBrokerCommand() . ' ' . ($logFile == '/dev/null' ? '> ' : '>> ') . escapeshellcmd($logFile) . ' 2>&1 & echo $!', $op);

            $pid = intval($op[0]);
        }

        return $ok;
    }

    /**
     * Gets start broker command
     *
     * @return string Returns start broker cmd
     */
    public static function getStartBrokerCommand()
    {
        return self::getStartPhpScriptCommand('/cron/broker.php');
    }

    /**
     * Gets start client command
     *
     * @return string Returns start client cmd
     */
    public static function getStartClientCommand()
    {
        return self::getStartPhpScriptCommand('/cron/client.php');
    }

    /**
     * Gets start worker command
     *
     * @return string Returns start worker cmd
     */
    public static function getStartWorkerCommand()
    {
        return self::getStartPhpScriptCommand('/cron/worker.php');
    }

    /**
     * Gets start php script command
     *
     * @param    string    $script  relative path from app folder
     * @return   string    Returns start php script CMD
     */
    public static function getStartPhpScriptCommand($script)
    {
        return PHP_BINARY  . ' '. realpath(APPPATH . $script);
    }

    /**
     * Terminates broker
     */
    public static function terminateBroker()
    {
        self::terminateByFilter(preg_replace('/^.+ php /', 'php ', self::getStartBrokerCommand()), false);
    }

    /**
     * Terminates all clients
     */
    public static function terminateClients()
    {
        self::terminateByFilter(preg_replace('/^.+ php /', 'php ', self::getStartClientCommand()), true);
    }

    /**
     * Gracefully terminates processes by start command key
     *
     * @param   string   $cmd         CMD used as the start
     * @param   string   $gracefully  optional Whether it should send SIGTERM rather than kill -9 forcefully
     */
    public static function terminateByFilter($cmd, $gracefully = true)
    {
        $op = [];

        exec('ps x -o pid,command | egrep -v "ps x -o pid,command|egrep " | egrep ' . escapeshellarg($cmd), $op);

        if (!empty($op)) {
            foreach ($op as $str) {
                $pid = substr(ltrim($str), 0, strpos(ltrim($str), ' '));

                if (!empty($pid)) {
                    if ($gracefully) {
                        posix_kill($pid, SIGTERM);
                    } else {
                        exec('kill -9 ' . $pid);
                    }
                }
            }
        }
    }

    /**
     * Performs health chek
     *
     * @throws    \Exception
     */
    public static function healthcheck()
    {
        //Verifying whether broker is running and starting broker if not.
        $healthy = self::ensureBrokerRunning();

        if (!$healthy) {
            //Waiting while broker is up
            sleep(2);
            //Retrying to check status
            $healthy = self::ensureBrokerRunning();
        }

        if (!$healthy) {
            //Could not start broker
            throw new Exception("Could not start 0MQ MDP Broker");
        }
    }
}