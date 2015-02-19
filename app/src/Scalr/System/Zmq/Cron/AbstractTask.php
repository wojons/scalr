<?php
namespace Scalr\System\Zmq\Cron;

use Exception;
use ArrayObject;
use ZMQException;
use Scalr\System\Zmq\Exception\TaskException;
use Scalr\System\Zmq\Mdp\AsynClient;
use Scalr\System\Zmq\Zmsg;
use Scalr\System\Zmq\Mdp\Client;
use Scalr\Util\Cron\CronExpression;
use Scalr\LoggerTrait;

/**
 * AbstractTask class
 *
 * Task queue boilerplate.
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0.1 (10.09.2014)
 */
abstract class AbstractTask implements TaskInterface
{
    use LoggerTrait;

    /**
     * Task name
     *
     * @var string
     */
    protected $name;

    /**
     * Task queue
     *
     * @var \ArrayObject
     */
    protected $queue;

    /**
     * Identifiers of the workers
     *
     * @var array
     */
    private $pids;

    /**
     * The list of the PID of workers to disconnect
     *
     * It looks like array(PID => address)
     *
     * @var array
     */
    private $toDisconnect;

    /**
     * Payload class name
     *
     * @var string
     */
    private $payloadClass = 'Scalr\\System\\Zmq\\Cron\\Payload';

    /**
     * Task config
     *
     * @var object
     */
    private $config;

    /**
     * The time when memory usage prints to log
     *
     * @var int
     */
    private $lastMemoryUsageTime = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        $basename = preg_replace('/^.+\\\\(\w+)$/', '$1', get_class($this));

        $this->name = \Scalr::decamelize($basename);

        $this->pids = [];
        $this->toDisconnect = [];

        $this->setLogger(\Scalr::getContainer()->logger($this->name)->setLevel($this->config()->log_level));

        //It is possible to redefine default payload class for the task
        if (file_exists(__DIR__ . '/Payload/' . $basename . 'Payload.php')) {
            $this->payloadClass = __NAMESPACE__ . '\\Payload\\' . $basename . 'Payload';
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::getName()
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::onResponse()
     */
    public function onResponse(AbstractPayload $payload)
    {
        //This method may be overriden if it's needed
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::onCompleted()
     */
    public function onCompleted()
    {
        //This method may be overriden if it's needed
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::run()
     */
    public function run()
    {
        $config = $this->config();

        $payloadClass = $this->payloadClass;

        if (!$config || !$config->enabled) {
            //This task has not been enabled to run
            return;
        }

        //Preparing task queue
        $this->queue = $this->enqueue();

        //Checking whether queue returns negotiated object type
        if (!($this->queue instanceof ArrayObject)) {
            throw new TaskException(sprintf("%s::enqueue() should return ArrayObject.", get_class($this)));
        }

        if (!$config->daemon && $config->workers <= 1) {
            //As the number of workers is one, we may process task queue in the same process
            foreach ($this->queue as $request) {
                /* @var $payload \Scalr\System\Zmq\Cron\AbstractPayload */
                $payload = (new $payloadClass())->setId();

                try {
                    $payload->setBody($this->worker($request));
                    $payload->code = 200;
                } catch (Exception $e) {
                    $this->getLogger()->error("Worker %s failed with exeption:%s - %s", $this->getName(), get_class($e), $e->getMessage());
                    $payload = $payload->error(500, $e->getMessage());
                }

                $this->onResponse($payload);
                unset($payload);
            }

            $this->onCompleted();

            return;
        } else {
            //Processing task queue with ZMQ MDP

            //If queue is empty nothing to do
            if (!$config->daemon && $this->queue->count() == 0) {
                return;
            }

            try {
                $this->launchClient();
            } catch (Exception $e) {
                //Kill them all
                $this->shutdown();
                throw $e;
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::shutdown()
     */
    public function shutdown()
    {
        static $once = 0;

        if ($once++) return;

        foreach ($this->pids as $pid) {
            $this->terminateWorker($pid);
        }
    }

    /**
     * Terminates one worker by specified pid
     *
     * @param    int    $pid   The identifier of the process
     */
    private function terminateWorker($pid)
    {
        $op = [];

        exec('ps x -o command -p ' . intval($pid) . ' | grep -v "ps x -o command|grep " | grep -i "' . $this->name . '"', $op);

        if (isset($op[1])) {
            $this->getLogger()->debug("Terminating child process PID:%d", $pid);

            //Process is running. Terminate it.
            posix_kill($pid, SIGTERM);
        }

        if (isset($this->pids[$pid])) {
            unset($this->pids[$pid]);
        }
    }

    /**
     * Checks whether service is registered with the broker
     *
     * @param   string    $serviceName optional The name of the service.
     *
     * @return  int|bool  Returns the number of registered workers if service has been registered with the broker or
     *                    boolean FALSE otherwise
     */
    public function isServiceRegistered($serviceName = null)
    {
        $client = (new Client(\Scalr::config('scalr.crontab.sockets.broker')))
            ->setTimeout(1000)
            ->setRetries(1)
            ->setLogger(\Scalr::getContainer()->logger('Mdp\\Client')->setLevel(\Scalr::config('scalr.crontab.log_level')))
            ->connect()
        ;

        $mmiReq = new Zmsg();
        $mmiReq->push($serviceName ?: $this->name);

        $mmiRep = $client->send("mmi.service", $mmiReq);

        if ($mmiRep) {
            $code = $mmiRep->pop();
            $workers = $code == '200' ? (int)$mmiRep->pop() : 0;
        }

        return $mmiRep && $code == '200' ? $workers : (isset($code) ? 0 : false);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::launch()
     */
    public function launch()
    {
        $op = [];

        $config = $this->config();

        $args = '--name=' . $this->name;

        exec(
            Launcher::getStartClientCommand() . ' ' . $args . ' '
          . ($config->log == '/dev/null' ? '>' : '>>') . ' ' . escapeshellcmd($config->log) . ' 2>&1 & echo $!'
          , $op
        );

        $this->log("DEBUG", "Launching %s client PID:%d", $this->name, intval($op[0]));

        return intval($op[0]);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::addWorker()
     */
    public function addWorker($address = null)
    {
        $op = [];

        $config = $this->config();

        $args = '--name=' . ($address ?: $this->name);

        exec(
            Launcher::getStartWorkerCommand() . ' ' . $args . ' '
          . ($config->log == '/dev/null' ? '>' : '>>') . ' ' . escapeshellcmd($config->log) . ' 2>&1 & echo $!'
          , $op
        );

        $this->getLogger()->debug("Adding %s worker PID:%d", ($address ?: $this->name), intval($op[0]));

        return intval($op[0]);
    }

    /**
     * It launches pool of workers
     *
     * @param   string   $address  optional An address to override the service name
     */
    protected function launchWorkers($address = null)
    {
        //Minimum number of the workers that should be
        $workers = $this->config()->workers;

        $service = $address ?: $this->name;

        //Number of the workers which are working now
        $availableWorkers = (int) $this->isServiceRegistered($service);

        $this->log("DEBUG", "%d avalilable workers for %s service", $availableWorkers, $service);

        //Service might be registered but workers has already gone
        if ($availableWorkers < $workers) {
            //Workers have not been launched yet. Starting them
            for ($i = $availableWorkers; $i < $workers; ++$i) {
                //It is important to save a PID of the process
                $pid = $this->addWorker($service);
                $this->pids[$pid] = $pid;
            }
        }
    }

    /**
     * Runs ZMQ MDP Asynchronous Client
     *
     * @throws Exception
     */
    protected function launchClient()
    {
        $this->launchWorkers();

        //We don't even need to start client if queue is empty
        if ($this->queue->count() == 0) {
            $this->log('DEBUG', "It does not need to start major-domo client as queue is empty.");
            return;
        }

        $this->log('DEBUG', "Launching %s 0mq mdp client", $this->name);

        $session = (new AsynClient(\Scalr::config('scalr.crontab.sockets.broker'), true))
            ->setLogger(\Scalr::getContainer()->logger('Mdp\\AsynClient')->setLevel(\Scalr::config('scalr.crontab.log_level')))
            ->setTimeout(\Scalr::config('scalr.crontab.heartbeat.delay') * \Scalr::config('scalr.crontab.heartbeat.liveness') * 2)
            ->connect()
        ;

        $this->log('DEBUG', 'Sending request messages to broker');

        $payloadClass = $this->payloadClass;

        //The number of the requests sent
        $count = 0;

        //Array of the messages which are sent
        $sentMessages = [];

        //Sending loop
        foreach ($this->queue as $index => $req) {
            //Creates standard payload for zmq messaging
            $payload = (new $payloadClass($req))->setId();
            $sentMessages[$payload->getId()] = $payload;

            $request = new Zmsg();
            $request->setLast(serialize($payload));

            //Sends a message to worker
            if ($payload instanceof PayloadRouterInterface) {
                $session->send($payload->getAddress($this), $request);
            } else {
                $session->send($this->name, $request);
            }

            $count++;

            //Removing message from queue
            unset($this->queue[$index]);
        }

        //Cleanup queue
        unset($this->queue);

        $this->log('DEBUG', 'Polling results');

        //Receiving loop
        for ($i = 0; $i < $count; $i++) {
            $msg = $session->recv();

            if (!$msg) {
                // Interrupt or failure
                $this->getLogger()->fatal("Some worker failed!");
                break;
            }

            //We are having deal with serialized data
            $payload = @unserialize($msg->getLast());

            if (!($payload instanceof AbstractPayload)) {
                throw new TaskException(sprintf("Unexpected reply from worker: '%s'.", $msg->getLast()));
            }

            //Checks if worker reaches a memory limit
            if (!empty($payload->dw)) {
                $this->toDisconnect[$payload->dw] = ($payload instanceof PayloadRouterInterface ? $payload->getAddress($this) : $this->name);
                $this->log("DEBUG", "Client got PID:%d from the worker %s to disconnect", $payload->dw, $this->toDisconnect[$payload->dw]);
            }

            if (!isset($sentMessages[$payload->getId()])) {
                //Message comes from previous session?
                $this->getLogger()->warn("Strange message came from another session. Payload:%s", var_export($payload, true));
                $count++;
            } else {
                //We get response so remove record
                unset($sentMessages[$payload->getId()]);

                //Triggers onResponse callback
                $this->onResponse($payload);
            }
        }

        if (!empty($this->toDisconnect) && $this->config()->daemon) {
            foreach ($this->toDisconnect as $pid => $address) {
                //Terminates worker
                $this->terminateWorker($pid);

                //We need to get up a replacement for that one
                $pid = $this->addWorker($address);
                //It is important to save a PID of the process to be able terminate all workers along with client
                $this->pids[$pid] = $pid;
            }

            //Resets event
            $this->toDisconnect = [];
            usleep(100000);
        }

        $this->onCompleted();
    }

    /**
     * Gets config for this cron
     *
     * @return  object Returns configuration options for the task
     */
    public function config()
    {
        if ($this->config === null) {
            $cfg = \Scalr::getContainer()->config;

            //Class name is the name of the data bag in the config
            $databag = 'scalr.crontab.services.' . $this->name;

            //Config might be undefined. In this case service should be disabled from running.
            $this->config = $cfg->defined($databag) ? (object)$cfg->get($databag) : null;

            if ($this->config === null) {
                throw new Exception(sprintf("Config section %s is undefined.", $databag));
            }

            if (!empty($this->config->time)) {
                //Injects cron expression instance into config
                $this->config->cronExpression = new CronExpression(
                    $this->config->time, (isset($this->config->timezone) ? $this->config->timezone : null)
                );
            }

            // Forces default memory_limit to set 70% of php.ini value for the demonized task
            if ($this->config->daemon && empty($this->config->memory_limit)) {
                $this->config->memory_limit = round(ini_get('memory_limit') * 0.7);
                if ($this->config->memory_limit <= 0) {
                    $this->config->memory_limit = 400;
                }
            }
        }

        return $this->config;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::checkMemoryUsage()
     */
    public function checkMemoryUsage()
    {
        $config = $this->config();

        if (!empty($config->memory_limit)) {
            $usage = explode(PHP_EOL, shell_exec(sprintf('ps -o rss -p %s', getmypid())));
            if (!empty($usage[1])) {
                $usage = trim($usage[1]) / 1024;
            } else {
                $usage = memory_get_usage() / 1024 / 1024;
            }

            if ($usage > $config->memory_limit) {
                $this->log('WARN', "Memory limit of %d Mb has been reached. Current usage is %0.3f Mb.", $config->memory_limit, $usage);
                return false;
            } else {
                if ((time() - $this->lastMemoryUsageTime) > 600) {
                    $this->lastMemoryUsageTime = time();
                    $this->log('SERVICE', 'Memory usage: %0.2f MB', $usage);
                }
            }
        }

        return true;
    }
}