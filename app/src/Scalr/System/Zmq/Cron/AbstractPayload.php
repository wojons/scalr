<?php
namespace Scalr\System\Zmq\Cron;

/**
 * Abstract request payload
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0.1 (10.09.2014)
 */
abstract class AbstractPayload
{
    /**
     * Identifier of the message.
     *
     * Binary string
     *
     * @var string
     */
    protected $id;

    /**
     * Payload
     *
     * @var mixed
     */
    public $body;

    /**
     * Response code
     *
     * @var int
     */
    public $code;

    /**
     * The PID of the worker to disconnect due to memory limit
     *
     * @var int
     */
    public $dw;

    /**
     * Constructor
     *
     * @param   mixed  $body  optional The message body
     */
    public function __construct($body = null)
    {
        $this->body = $body;
    }

    public function __sleep()
    {
        return ['id', 'body', 'code', 'dw'];
    }

    /**
     * Sets or generate identifier of the message
     *
     * @param   string   $uuid  optional UUID
     * @return  \Scalr\System\Zmq\Cron\AbstractPayload
     */
    public function setId($uuid = null)
    {
        $this->id = $uuid === null ? $this->generateUuid() : $uuid;

        return $this;
    }

    /**
     * Sets payload
     *
     * @param   mixed   $body  Payload
     * @return  \Scalr\System\Zmq\Cron\AbstractPayload
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Gets identifier associated with the payload
     *
     * @return  string  Returns identifier associated with the payload
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Gets auto generated unique identifier
     *
     * @return string  Returns UUID
     */
    protected function generateUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Gets ErrorPayload
     *
     * @param    int    $code     The error code
     * @param    string $message  The error message
     * @return   \Scalr\System\Zmq\Cron\ErrorPayload Returns error payload
     */
    public function error($code, $message)
    {
        $error = new ErrorPayload();
        $error->setId($this->getId());
        $error->body = $this->body;
        $error->code = $code;
        $error->message = $message;

        return $error;
    }
}