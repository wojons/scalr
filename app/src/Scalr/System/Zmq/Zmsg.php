<?php
namespace Scalr\System\Zmq;

/**
 * Multipart message class
 *
 * It has been originated from 0mq documentation with some modification.
 * Many thanks to Ian Barber.
 *
 * @since 5.0 (05.09.2014)
 */
class Zmsg
{

    /**
     * Parts of the messages
     *
     * @var array
     */
    private $parts = [];

    /**
     * Socket to communicate
     *
     * @var \ZMQSocket
     */
    private $socket;

    /**
     * Constructor
     *
     * @param \ZMQSocket $socket optional socket for sending/receiving.
     */
    public function __construct(\ZMQSocket $socket = null)
    {
        $this->socket = $socket;
    }

    /**
     * Set the internal socket to use for communications.
     *
     * @param \ZMQSocket $socket socket for communications
     * @return Zmsg
     */
    public function setSocket(\ZMQSocket $socket)
    {
        $this->socket = $socket;

        return $this;
    }

    /**
     * Receives message from socket
     *
     * Creates a new message and returns it.
     * Blocks on recv if socket is not ready for input.
     *
     * @return Zmsg
     * @throws \Exception
     */
    public function recv()
    {
        if (!isset($this->socket)) {
            throw new \Exception("No socket supplied");
        }

        $this->parts = [];

        while (true) {
            $this->parts[] = $this->socket->recv();

            if (!$this->socket->getSockOpt(\ZMQ::SOCKOPT_RCVMORE)) {
                break;
            }
        }

        return $this;
    }

    /**
     * Sends message to socket.
     *
     * @param   boolean $clear optional Whether it shoud destroy message parts
     * @return  Zmsg
     * @throws  \Exception
     */
    public function send($clear = true)
    {
        if (!isset($this->socket)) {
            throw new \Exception("No socket supplied");
        }

        $count = count($this->parts);

        foreach ($this->parts as $part) {
            $this->socket->send($part, (--$count ? \ZMQ::MODE_SNDMORE : null));
        }

        if ($clear) {
            unset($this->parts);

            $this->parts = [];
        }

        return $this;
    }

    /**
     * Report size of message
     *
     * @return int Returns the size of the message
     */
    public function parts()
    {
        return count($this->parts);
    }

    /**
     * Returns the body
     *
     * @return  string Returns the last message part
     */
    public function getLast()
    {
        $pos = count($this->parts) - 1;

        return $pos < 0 ? null : $this->parts[$pos];
    }

    /**
     * Sets the message body to provided string.
     *
     * @param  string $body The message to set
     * @return Zmsg
     */
    public function setLast($body)
    {
        $pos = count($this->parts) - 1;

        $this->parts[$pos > 0 ? $pos : 0] = $body;

        return $this;
    }

    /**
     * Appends frame to end of the message
     *
     * @param    string    $body  The body
     * @return   \Scalr\System\Zmq\Zmsg
     */
    public function append($body)
    {
        $this->parts[] = $body;

        return $this;
    }

    /**
     * Prepends the message to the beginning
     *
     * @param   string $part  The message to prepend
     * @return  Zmsg
     */
    public function push($part)
    {
        array_unshift($this->parts, $part);

        return $this;
    }

    /**
     * Pops the message off front of message parts
     *
     * @return  string
     */
    public function pop()
    {
        return array_shift($this->parts);
    }

    /**
     * Return the address of the message
     *
     * @return string  The address of the message
     */
    public function address()
    {
        $address = count($this->parts) ? $this->parts[0] : null;

        return (strlen($address) == 17 && $address[0] == 0) ? "@" . bin2hex($address) : $address;
    }

    /**
     * Wraps message in new address envelope.
     *
     * If delim is not null, creates two part envelope.
     *
     * @param   string $address The address
     * @param   string $delim   optional Delimiter
     * @return  Zmsg
     */
    public function wrap($address, $delim = null)
    {
        if ($delim !== null) {
            $this->push($delim);
        }

        if ($address[0] == '@' && strlen($address) == 33) {
            $address = pack("H*", substr($address, 1));
        }

        $this->push($address);

        return $this;
    }

    /**
     * Unwraps outer message envelope and returns address
     *
     * Discards empty message part after address, if any.
     *
     * @return string  Returns address
     */
    public function unwrap()
    {
        $address = $this->pop();

        if (!$this->address()) {
            $this->pop();
        }

        return $address;
    }

    /**
     * Dumps the contents to a string, for debugging and tracing.
     *
     * @return string
     */
    public function __toString()
    {
        $string = '';

        foreach ($this->parts as $index => $part) {
            $len = strlen($part);

            if ($len == 17 && $part[0] == 0) {
                $part = "@" . bin2hex($part);
                $len = strlen($part);
            }

            $string .= sprintf("[%03d] %s%s", $len, $part, PHP_EOL);
        }

        return $string;
    }

    /**
     * Saves a msg to a file
     *
     * @param   resource   $fp  file handler to save
     * @return  Zmsg
     */
    public function save($fp)
    {
        foreach ($this->parts as $part) {
            fwrite($fp, chr(strlen($part)));

            if (strlen($part) > 0) {
                fwrite($fp, $part);
            }
        }

        return $this;
    }

    /**
     * Load a message saved with the save function
     *
     * @param   resource   $fp  File handler to load
     * @return  Zmsg
     */
    public function load($fp)
    {
        while (!feof($fp) && $size = fread($fp, 1)) {
            $this->parts[] = ord($size) > 0 ? fread($fp, ord($size)) : '';
        }

        return $this;
    }
}