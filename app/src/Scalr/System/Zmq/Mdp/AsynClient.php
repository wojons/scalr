<?php
namespace Scalr\System\Zmq\Mdp;

use ZMQContext, ZMQSocket, ZMQPoll, ZMQ;
use Scalr\System\Zmq\Zmsg;
use Scalr\System\Zmq\Exception\MdpException;
use Scalr\LoggerTrait;

/**
 * Majordomo Protocol Asynchronous Client API
 *
 * Implements the MDP/Worker spec at http://rfc.zeromq.org/spec:7
 *
 * @since 5.0 (08.09.2014)
 */
class AsynClient
{
    use LoggerTrait;

    /**
     * Timeout msecs
     */
    const TIMEOUT = 2500;

    /**
     * DSN of the broker
     *
     * @var string
     */
    private $broker;

    /**
     * Context
     *
     * @var \ZMQContext
     */
    private $context;

    /**
     * Socket to broker
     *
     * @var \ZMQSocket
     */
    private $client;

    /**
     * Verbosity
     *
     * Prints activity to stdout
     *
     * @var boolean
     */
    private $verbose;

    /**
     * Request timeout, msecs
     *
     * @var int
     */
    private $timeout;

    /**
     * Constructor
     *
     * @param   string  $broker  DSN of broker
     * @param   boolean $verbose optional Whether is should out verbose info
     */
    public function __construct($broker, $verbose = false)
    {
        $this->broker = $broker;
        $this->context = new ZMQContext();
        $this->verbose = $verbose;
        $this->timeout = self::TIMEOUT;
    }

    /**
     * Connects or reconnect to broker
     *
     * @return  AsynClient
     */
    public function connect()
    {
        if ($this->client) {
            unset($this->client);
        }

        $this->client = new ZMQSocket($this->context, ZMQ::SOCKET_DEALER);
        $this->client->setSockOpt(ZMQ::SOCKOPT_LINGER, 0);
        $this->client->connect($this->broker);

        if ($this->verbose) {
            $this->log("ZMQDEBUG", "connecting to broker at %s...", $this->broker);
        }

        return $this;
    }

    /**
     * Sets request timeout
     *
     * @param  int     $timeout Request timeout (msecs)
     * @return AsynClient
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Send request to broker and get reply by hook or crook.
     *
     * Takes ownership of request message and destroys it when sent.
     *
     *
     * @param   string  $service  The name of the service
     * @param   Zmsg    $request  Request message
     * @return  Zmsg    Returns the reply message or NULL if there was no reply.
     */
    public function send($service, Zmsg $request)
    {
        // Prefix request with protocol frames
        // Frame 0: empty (REQ emulation)
        // Frame 1: "MDPCxy" (six bytes, MDP/Client x.y)
        // Frame 2: Service name (printable string)
        $request->push($service);
        $request->push(Mdp::CLIENT);
        $request->push("");

        if ($this->verbose) {
            $this->log('ZMQDEBUG', "send request to '%s' service:\n--\n%s", $service, (string)$request);
        }

        $request->setSocket($this->client)->send();
    }

    /**
     * Receives a reply
     *
     * Returns the reply message or NULL if there was no reply.
     * Does not attempt to recover from a broker failure, this is not possible
     * without storing all unanswered requests and resending them all ...
     */
    public function recv()
    {
        $read = $write = array();

        // Poll socket for a reply, with timeout
        $poll = new ZMQPoll();
        $poll->add($this->client, ZMQ::POLL_IN);
        $events = $poll->poll($read, $write, $this->timeout);

        // If we got a reply, process it
        if ($events) {
            $msg = new Zmsg($this->client);
            $msg->recv();

            if ($this->verbose) {
                $this->log('ZMQDEBUG', "received reply:\n--\n%s", (string)$msg);
            }

            if ($msg->parts() < 4) {
                throw new MdpException("More than 3 parts are expected in the message.");
            }

            $msg->pop();

            $header = $msg->pop();

            if ($header !== Mdp::CLIENT) {
                throw new MdpException(sprintf("%s is expected.", Mdp::CLIENT));
            }

            $repService = $msg->pop();

            return $msg;
        } else {
            if ($this->verbose) {
                $this->log('WARN', "permanent error, abandoning request");
            }

            return;
        }
    }
}