<?php
namespace Scalr\System\Zmq\Mdp;

use ZMQ, ZMQContext, ZMQSocket, ZMQPoll, stdClass;
use Scalr\System\Zmq\Zmsg;
use Scalr\LoggerTrait;

/**
 * Majordomo Protocol Broker API
 *
 * Implements the MDP/Broker spec at http://rfc.zeromq.org/spec:7
 *
 * @since 5.0 (08.09.2014)
 */
class Broker
{
    use LoggerTrait;

    const HEARTBEAT_LIVENESS = Mdp::HEARTBEAT_LIVENESS;

    /**
     * Heartbeat interval, msecs
     */
    const HEARTBEAT_INTERVAL = Mdp::HEARTBEAT_DELAY;

    /**
     * Context
     *
     * @var \ZMQContext
     */
    private $ctx;

    /**
     * Socket for clients and workers
     *
     * @var \ZMQSocket
     */
    private $socket;

    /**
     * Broker's DSN endpoint
     *
     * @var string
     */
    private $endpoint;

    /**
     * Hashes of known services
     *
     * @var array
     */
    private $services = [];

    /**
     * Hashes of known workers
     *
     * @var array
     */
    private $workers = [];

    /**
     * List of waiting workers
     *
     * @var array
     */
    private $waiting = [];

    /**
     * Verbosity
     *
     * @var boolean
     */
    private $verbose = false;

    /**
     * Heartbeat management
     * When to send HEARTBEAT
     *
     * @var float
     */
    private $heartbeatAt;

    /**
     * How many attempts left
     *
     * @var int
     */
    private $liveness;

    /**
     * Heartbeat delay, msecs
     *
     * @var int
     */
    private $heartbeat;

    /**
     * Heartbeat Expiry delay, msecs
     *
     * @var int
     */
    private $heartbeatExpiry;

    /**
     * Constructor
     *
     * @param   boolean   $verbose  Turns on verbosity for debug purposes
     */
    public function __construct($verbose = false)
    {
        $this->ctx = new ZMQContext();
        $this->socket = new ZMQSocket($this->ctx, ZMQ::SOCKET_ROUTER);
        $this->verbose = $verbose;
        $this->liveness = self::HEARTBEAT_LIVENESS;
        $this->setHeartbeat(self::HEARTBEAT_INTERVAL);
    }

    /**
     * Sets heartbeat interval
     *
     * @param  int      $heartbeat Heartbeat interval, msecs
     * @return Broker
     */
    public function setHeartbeat($heartbeat)
    {
        $this->heartbeat = $heartbeat;
        $this->updateHeartbeatExpiry();

        return $this;
    }

    /**
     * Sets heartbeat liveness
     *
     * @param  int      $liveness  Heartbeat liveness
     * @return Broker
     */
    public function setLiveness($liveness)
    {
        $this->liveness = intval($liveness);
        $this->updateHeartbeatExpiry();

        return $this;
    }

    /**
     * Updates heartbeat expiry based on updated options
     */
    protected function updateHeartbeatExpiry()
    {
        $this->heartbeatExpiry = $this->heartbeat * $this->liveness;
        $this->heartbeatAt = microtime(true) + ($this->heartbeat / 1000);
    }

    /**
     * Binds broker to endpoint
     *
     * We use a single socket for both clients and workers.
     *
     * @param   string   $endpoint
     * @return  Broker
     */
    public function bind($endpoint)
    {
        $this->endpoint = $endpoint;

        $this->socket->bind($this->endpoint);

        if ($this->verbose) {
            $this->log("ZMQDEBUG", "MDP broker/0.1.1 is active at %s", $this->endpoint);
        }

        return $this;
    }

    /**
     * This is the main listen and process loop
     */
    public function listen()
    {
        $read = $write = array();

        // Get and process messages forever or until interrupted
        while (true) {
            $poll = new ZMQPoll();
            $poll->add($this->socket, ZMQ::POLL_IN);

            $events = $poll->poll($read, $write, $this->heartbeat);

            // Process next input message, if any
            if ($events) {
                $zmsg = new Zmsg($this->socket);
                $zmsg->recv();

                if ($this->verbose) {
                    $this->log("ZMQDEBUG", "received message:\n--\n%s", (string)$zmsg);
                }

                $sender = $zmsg->pop();
                $empty = $zmsg->pop();
                $header = $zmsg->pop();

                if ($header == Mdp::CLIENT) {
                    $this->processClient($sender, $zmsg);
                } elseif ($header == Mdp::WORKER) {
                    $this->processWorker($sender, $zmsg);
                } else {
                    if ($this->verbose) {
                        $this->log("ERROR", "invalid message\n--\n%s", (string)$zmsg);
                    }
                }
            }

            // Disconnect and delete any expired workers
            // Send heartbeats to idle workers if needed
            if (microtime(true) > $this->heartbeatAt) {
                $this->purgeWorkers();

                foreach ($this->workers as $worker) {
                    $this->workerSend($worker, Mdp::WORKER_HEARTBEAT);
                }

                $this->updateHeartbeatExpiry();
            }
        }
    }

    /**
     * Delete any idle workers that haven't pinged us in a while.
     * We know that workers are ordered from oldest to most recent.
     */
    public function purgeWorkers()
    {
        foreach ($this->waiting as $id => $worker) {
            if (microtime(true) < $worker->expiry) {
                // Worker is alive, we're done here
                break;
            }

            if ($this->verbose) {
                $this->log("ZMQDEBUG", "deleting expired worker: %s", bin2hex($worker->identity));
            }

            $this->deleteWorker($worker);
        }
    }

    /**
     * Locate or create new service entry
     *
     * @param   string   $name  The name of the service
     * @return  stdClass Returns service object
     */
    public function fetchService($name)
    {
        if (!isset($this->services[$name])) {
            $service = new stdClass();
            $service->name = $name;
            $service->requests = [];
            $service->waiting = [];

            $this->services[$name] = $service;
        }

        return $this->services[$name];
    }

    /**
     * Dispatch requests to waiting workers as possible
     *
     * @param   \stdClass              $service  The service object
     * @param   \Scalr\System\Zmq\Zmsg $msg      optional Message
     */
    public function dispatchService($service, $msg = null)
    {
        if ($msg !== null) {
            $service->requests[] = $msg;
        }

        $this->purgeWorkers();

        while (count($service->waiting) && count($service->requests)) {
            $worker = array_shift($service->waiting);
            $msg = array_shift($service->requests);

            $this->rmwrk($worker, $this->waiting);

            $this->workerSend($worker, Mdp::WORKER_REQUEST, null, $msg);
        }
    }

    /**
     * Handle internal service according to 8/MMI specification
     *
     * @param    string                 $frame  The frame
     * @param    \Scalr\System\Zmq\Zmsg $msg    The message
     */
    protected function handleInternalService($frame, $msg)
    {
        if ($frame == "mmi.service") {
            $name = $msg->getLast();
            $service = isset($this->services[$name]) ? $this->services[$name] : null;
            $code = !empty($service->workers) ? "200" : "404";
        } else {
            $code = "501";
        }

        $client = $msg->unwrap();

        $msg->setLast($code);

        //NOTE! We changed a bit ZMQ MDP protocol here.
        //The number of registered workers for the service follows the status frame.
        $msg->append(sprintf("%s", isset($service->workers) ? intval($service->workers) : "0"));

        $msg->push($frame);
        $msg->push(Mdp::CLIENT);

        $msg->wrap($client, "");

        if ($this->verbose) {
            $this->log("ZMQDEBUG", "responding mmi.service:\n--\n%s", (string)$msg);
        }

        $msg->setSocket($this->socket)->send();
    }

    /**
     * Creates or fetches worker if necessary
     *
     * @param    string     $address  The address of the worker
     * @return   \stdClass  Returns worker object
     */
    public function fetchWorker($address)
    {
        if (!isset($this->workers[$address])) {
            $worker = new stdClass();
            $worker->identity = $address;

            $this->workers[$address] = $worker;

            if ($this->verbose) {
                $this->log("ZMQDEBUG", "registering a new worker: %s", bin2hex($address));
            }
        }

        return $this->workers[$address];
    }

    /**
     * Removes a worker
     *
     * @param   \stdClass  $worker      The worker object
     * @param   boolean    $disconnect  optional Whether it should send disconnect message to worker
     */
    public function deleteWorker($worker, $disconnect = false)
    {
        $waiting = null;

        if ($disconnect) {
            $this->workerSend($worker, Mdp::WORKER_DISCONNECT);
        }

        if (isset($worker->service)) {
            $waiting = $this->rmwrk($worker, $worker->service->waiting);
        } else {
            if ($this->verbose) {
                $this->log('ZMQDEBUG', "service for worker %s is undefined so that it cannot decrease the number of workers", bin2hex($worker->identity));
            }
        }

        $found = $this->rmwrk($worker, $this->waiting);

        if ($waiting || $found && $waiting === false || !empty($this->workers[$worker->identity]->service->workers)) {
            $worker->service->workers--;

            // Overcautious measure
            if ($worker->service->workers < 0) {
                $worker->service->workers = 0;
            }

            if ($this->verbose) {
                $this->log('ZMQDEBUG', "The number of workers for the service %s was decreased to %d",
                    $worker->service->name, $worker->service->workers);
            }
        }

        unset($this->workers[$worker->identity]);
    }

    /**
     * Removes worker from passed array
     *
     * @param   \stdClass $worker A worker object
     * @param   array     $array  Collection of the workers
     * @return  boolean   Returns TRUE if worker has been found and removed or FALSE otherwise
     */
    private function rmwrk($worker, &$array)
    {
        $keys = array_keys($array, $worker);

        if (!empty($keys)) {
            foreach ($keys as $index) {
                unset($array[$index]);
            }

            return true;
        }

        return false;
    }

    /**
     * Process message sent to us by a worker
     *
     * @param   string                 $sender The address of the worker
     * @param   \Scalr\System\Zmq\Zmsg $msg    The message
     */
    public function processWorker($sender, $msg)
    {
        $command = $msg->pop();

        $workerReady = isset($this->workers[$sender]);

        $worker = $this->fetchWorker($sender);

        if ($command == Mdp::WORKER_READY) {
            if ($workerReady) {
                // Not first command in session
                $this->deleteWorker($worker, true);
            } elseif (strlen($sender) >= 4 && substr($sender, 0, 4) == 'mmi.') {
                // Reserved service name
                $this->deleteWorker($worker, true);
            } else {
                // Attach worker to service and mark as idle
                $serviceFrame = $msg->pop();
                $worker->service = $this->fetchService($serviceFrame);
                $worker->service->workers++;

                $this->waitWorker($worker);
            }
        } elseif ($command == Mdp::WORKER_REPLY) {
            if ($workerReady) {
                // Remove & save client return envelope and insert the
                // protocol header and service name, then rewrap envelope.
                $client = $msg->unwrap();
                $msg->push($worker->service->name);
                $msg->push(Mdp::CLIENT);
                $msg->wrap($client, "");
                $msg->setSocket($this->socket)->send();

                if ($this->verbose) {
                    $this->log("ZMQDEBUG", "worker replied:\n--\n%s", (string)$msg);
                }

                $this->waitWorker($worker);
            } else {
                $this->deleteWorker($worker, true);
            }
        } elseif ($command == Mdp::WORKER_HEARTBEAT) {
            if ($workerReady) {
                $worker->expiry = microtime(true) + ($this->heartbeatExpiry / 1000);
            } else {
                $this->deleteWorker($worker, true);
            }
        } elseif ($command == Mdp::WORKER_DISCONNECT) {
            $this->deleteWorker($worker, true);
            if ($this->verbose) {
                $this->log("ZMQDEBUG", "disconnect worker\n--\n%s", (string)$msg);
            }
        } else {
            if ($this->verbose) {
                $this->log("ERROR", "invalid input message\n--\n%s", (string)$msg);
            }
        }
    }

    /**
     * Sends message to worker
     *
     * @param   \stdClass $worker   The worker object
     * @param   string    $command  Command
     * @param   mixed     $option   optional Options
     * @param   Zmsg      $msg      optional The message
     */
    public function workerSend($worker, $command, $option = null, Zmsg $msg = null)
    {
        $msg = $msg !== null ? $msg : new Zmsg();

        // Stack protocol envelope to start of message
        if ($option) {
            $msg->push($option);
        }

        $msg->push($command);
        $msg->push(Mdp::WORKER);

        // Stack routing envelope to start of message
        $msg->wrap($worker->identity, "");

        if ($this->verbose) {
            $this->log("ZMQDEBUG", "sending %s to worker\n--\n%s",
                (isset(Mdp::$cmdname[$command]) ? Mdp::$cmdname[$command] : $command),
                (string)$msg);
        }

        $msg->setSocket($this->socket)->send();
    }

    /**
     * This worker is now waiting for work
     *
     * @param  \stdClass $worker The worker object
     */
    public function waitWorker($worker)
    {
        // Queue to broker and service waiting lists
        $this->waiting[] = $worker;

        $worker->service->waiting[] = $worker;
        $worker->expiry = microtime(true) + ($this->heartbeatExpiry / 1000);

        $this->dispatchService($worker->service, null);
    }

    /**
     * Process a request coming from a client
     *
     * @param   string $sender The address of the sender
     * @param   Zmsg   $msg    A message
     */
    public function processClient($sender, Zmsg $msg)
    {
        $serviceFrame = $msg->pop();

        $service = $this->fetchService($serviceFrame);

        // Set reply return address to client sender
        $msg->wrap($sender, "");

        if (substr($serviceFrame, 0, 4) == 'mmi.') {
            $this->handleInternalService($serviceFrame, $msg);
        } else {
            $this->dispatchService($service, $msg);
        }
    }
}