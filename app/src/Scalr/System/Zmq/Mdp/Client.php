<?php
namespace Scalr\System\Zmq\Mdp;

use ZMQ, ZMQContext, ZMQSocket, ZMQPoll;
use Scalr\System\Zmq\Zmsg;
use Scalr\System\Zmq\Exception\MdpException;
use Scalr\LoggerAwareTrait;

/**
 * Majordomo Protocol Client API
 *
 * Implements the MDP/Worker spec at http://rfc.zeromq.org/spec:7
 *
 * @since 5.0 (05.09.2014)
 */
class Client
{
    use LoggerAwareTrait;

    /**
     * Timeout msecs
     */
    const TIMEOUT = 2500;

    /**
     * Retries before it abandons
     *
     * @var int
     */
    const RETRIES = 3;

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
     * Request retries before it abandons
     *
     * @var int
     */
    private $retries;

    /**
     * Constructor
     *
     * @param  string  $broker   DSN of the broker
     * @param  boolean $verbose  optional Verbosity
     */
    public function __construct($broker, $verbose = false)
    {
        $this->broker = $broker;
        $this->context = new ZMQContext();
        $this->verbose = $verbose;
        $this->timeout = self::TIMEOUT;
        $this->retries = self::RETRIES;
    }

    /**
     * Connect or reconnect to broker
     *
     * @return Client
     */
    public function connect()
    {
        if ($this->client) {
            unset($this->client);
        }

        $this->client = new ZMQSocket($this->context, ZMQ::SOCKET_REQ);
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
     * @return Client
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Sets request retries
     *
     * @param   int   $retries  Request retries
     * @return  Client
     */
    public function setRetries($retries)
    {
        $this->retries = $retries;

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
        // Frame 1: "MDPCxy" (six bytes, MDP/Client)
        // Frame 2: Service name (printable string)
        $request->push($service);
        $request->push(Mdp::CLIENT);

        if ($this->verbose) {
            $this->log("ZMQDEBUG", "send request to '%s' service:\n--\n%s", $service, (string)$request);
        }

        $retries_left = $this->retries;

        $read = $write = array();

        while ($retries_left) {
            $request->setSocket($this->client)->send();

            // Poll socket for a reply, with timeout
            $poll = new ZMQPoll();
            $poll->add($this->client, ZMQ::POLL_IN);
            $events = $poll->poll($read, $write, $this->timeout);

            // If we got a reply, process it
            if ($events) {
                $request->recv();

                if ($this->verbose) {
                    $this->log("ZMQDEBUG", "received reply:\n--\n%s", $request);
                }

                if ($request->parts() < 3) {
                    throw new MdpException(sprintf("Expected more than 2 parts, but %d received", $request->parts()));
                }

                $header = $request->pop();

                if ($header !== Mdp::CLIENT) {
                    throw new MdpException(sprintf("Unexpected header %s, %s is expected", $header, Mdp::CLIENT));
                }

                $replyService = $request->pop();

                if ($replyService != $service) {
                    throw new MdpException(sprintf("Unexpected service %s, %s is expected.", $replyService, $service));
                }

                //Success
                return $request;
            } elseif ($retries_left--) {
                if ($this->verbose) {
                    $this->log("WARN", "no reply, reconnecting...");
                }

                // Reconnect
                $this->connect();

                // Resend message again
                $request->send();
            } else {
                if ($this->verbose) {
                    $this->log("ERROR", "permanent error, abandoning request");
                    break;
                }
            }
        }
    }
}