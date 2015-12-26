<?php
namespace Scalr\System\Zmq\Mdp;

use ZMQ, ZMQContext, ZMQSocket, ZMQPoll;
use Scalr\System\Zmq\Zmsg;
use Scalr\System\Zmq\Exception\MdpException;
use Scalr\LoggerAwareTrait;

/**
 * Majordomo Protocol Worker API
 *
 * Implements the MDP/Worker spec at http://rfc.zeromq.org/spec:7
 *
 * @since 5.0 (05.09.2014)
 */
class Worker
{
    use LoggerAwareTrait;

    /**
     * Reliability parameter.
     * 3 - 5 is reasonable
     */
    const HEARTBEAT_LIVENESS = Mdp::HEARTBEAT_LIVENESS;

    /**
     * Default heartbeat delay, msecs
     */
    const HEARTBEAT_DELAY = Mdp::HEARTBEAT_DELAY;

    /**
     * Default heartbeat reconnect, msecs
     */
    const HEARTBEAT_RECONNECT = 2500;

    /**
     * Context
     *
     * @var \ZMQContext
     */
    private $ctx;

    /**
     * DSN of the broker
     *
     * @var string
     */
    private $broker;

    /**
     * The name of the service
     *
     * @var string
     */
    private $service;

    /**
     * Socket to broker
     *
     * @var \ZMQSocket
     */
    private $worker;

    /**
     * Verbosity
     *
     * @var boolean
     */
    private $verbose = false;

    /**
     * When to send heartbeat
     *
     * Float microtime
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
     * Reconnect delay, msecs
     *
     * @var int
     */
    private $reconnect;

    /**
     * Internal flag
     *
     * @var boolean
     */
    private $expectReply;

    /**
     * Return address
     *
     * @var string
     */
    private $replyTo;

    /**
     * Constructor
     *
     * @param   string  $broker  DSN of the broker
     * @param   string  $service The name of the service
     * @param   boolean $verbose optional Should it output debug info
     */
    public function __construct($broker, $service, $verbose = false)
    {
        $this->ctx = new ZMQContext();
        $this->broker = $broker;
        $this->service = $service;
        $this->verbose = $verbose;
        $this->heartbeat = self::HEARTBEAT_DELAY;
        $this->reconnect = self::HEARTBEAT_RECONNECT;
    }

    /**
     * Sends message to broker
     *
     * If no msg is provided, creates one internally
     *
     * @param  string $command Worker command
     * @param  string $option  optional
     * @param  Zmsg   $msg     optional Zmsg object
     */
    public function send($command, $option = null, $msg = null)
    {
        $msg = $msg ? $msg : new Zmsg();

        if ($option) {
            $msg->push($option);
        }

        $msg->push($command);
        $msg->push(Mdp::WORKER);
        $msg->push("");

        if ($this->verbose) {
            $this->log("ZMQDEBUG", "sending %s to broker\n--\n%s",
                (isset(Mdp::$cmdname[$command]) ? Mdp::$cmdname[$command] : $command),
                (string)$msg);
        }

        $msg->setSocket($this->worker)->send();
    }

    /**
     * Connects or reconnect to broker
     *
     * @return  Worker
     */
    public function connect()
    {
        $this->worker = new ZMQSocket($this->ctx, ZMQ::SOCKET_DEALER);
        $this->worker->connect($this->broker);

        if ($this->verbose) {
            $this->log("ZMQDEBUG", "connecting to broker at %s...", $this->broker);
        }

        // Register service with broker
        $this->send(Mdp::WORKER_READY, $this->service, null);

        // If liveness hits zero, queue is considered disconnected
        $this->liveness = self::HEARTBEAT_LIVENESS;

        $this->updateHeartbeatExpiry();

        return $this;
    }

    /**
     * Sets heartbeat delay
     *
     * @param  int      $heartbeat Heartbeat delay, msecs
     * @return Worker
     */
    public function setHeartbeat($heartbeat)
    {
        $this->heartbeat = $heartbeat;
        $this->updateHeartbeatExpiry();

        return $this;
    }

    /**
     * Sets liventss
     *
     * @param  int      $liveness Liveness
     * @return Worker
     */
    public function setLiveness($liveness)
    {
        $this->liveness = intval($liveness);
        $this->updateHeartbeatExpiry();

        return $this;
    }

    /**
     * Updates heartbeat expiry
     */
    protected function updateHeartbeatExpiry()
    {
        $this->heartbeatAt = microtime(true) + ($this->heartbeat / 1000);
    }

    /**
     * Sets reconnect delay
     *
     * @param  int   $reconnect Reconnect delay, msecs
     * @return Worker
     */
    public function setReconnect($reconnect)
    {
        $this->reconnect = $reconnect;

        return $this;
    }

    /**
     * Send reply, if any, to broker and wait for next request.
     *
     * @param   Zmsg $reply  optional  Reply message object
     * @return  Zmsg Returns if there is a request to process
     */
    public function recv($reply = null)
    {
        // Format and send the reply if we were provided one
        if (!$reply && $this->expectReply) {
            throw new MdpException("Reply message is expected");
        }

        if ($reply) {
            $reply->wrap($this->replyTo);
            $this->send(Mdp::WORKER_REPLY, null, $reply);
        }

        $this->expectReply = true;

        $read = $write = [];

        while (true) {
            $poll = new ZMQPoll();
            $poll->add($this->worker, ZMQ::POLL_IN);

            $events = $poll->poll($read, $write, $this->heartbeat);

            if ($events) {
                $zmsg = new Zmsg($this->worker);
                $zmsg->recv();

                if ($this->verbose) {
                    $this->log("ZMQDEBUG", "received message from broker:\n--\n%s", (string) $zmsg);
                }

                $this->liveness = self::HEARTBEAT_LIVENESS;

                if ($zmsg->parts() < 3) {
                    throw new MdpException(sprintf("Expected more then 2 parts, but %d received", $zmsg->parts()));
                }

                $zmsg->pop();
                $header = $zmsg->pop();

                if ($header !== Mdp::WORKER) {
                    throw new MdpException(sprintf("Expected %s header, %s has been actually received", Mdp::WORKER, $header));
                }

                $command = $zmsg->pop();

                if ($command == Mdp::WORKER_REQUEST) {
                    // We should pop and save as many addresses as there are
                    // up to a null part, but for now, just save one…
                    $this->replyTo = $zmsg->unwrap();

                    // We have a request to process
                    return $zmsg;
                } elseif ($command == Mdp::WORKER_HEARTBEAT) {
                    // Do nothing for heartbeats
                } elseif ($command == Mdp::WORKER_DISCONNECT) {
                    $this->connect();
                } else {
                    if ($this->verbose) {
                        $this->log("ERROR", "invalid input message\n--\n%s", (string)$zmsg);
                    }
                }
            } elseif (--$this->liveness == 0) {
                // poll ended on timeout, $event being false
                if ($this->verbose) {
                    $this->log("WARN", "Disconnected from broker - exiting...\n");
                }

                //Exiting is deviation from the MDP protocol
                throw new MdpException("Disconnected from broker - exiting");
                //usleep($this->reconnect * 1000);
                //$this->connect();
            }

            // Send HEARTBEAT if it's time
            if (microtime(true) > $this->heartbeatAt) {
                $this->send(Mdp::WORKER_HEARTBEAT);
                $this->updateHeartbeatExpiry();
            }
        }
    }
}