<?php

namespace Scalr\Api\DataType;

/**
 * Class ApiMessage
 * @author  Andrii Penchuk <a.penchuk@scalr.com>
 * @since   5.6.14 (07.11.2015)
 */
class ApiMessage extends AbstractDataType
{
    /**
     * A machine-readable representation of the message
     *
     * @var string
     */
    public $code;

    /**
     * A human-readable representation of the message
     *
     * @var string
     */
    public $message;

    /**
     * Constructor
     * @param   string $code    optional A machine-readable API message
     * @param   string $message optional A human-readable API message
     */
    public function __construct($code = null, $message = null)
    {
        $this->code = $code ?: '';
        $this->message = $message ?: '';
    }
}