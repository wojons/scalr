<?php

namespace Scalr\Api\DataType;

/**
 * ErrorEnvelope object
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (02.03.2015)
 */
class ErrorEnvelope extends AbstractDataType
{
    /**
     * Meta object
     *
     * @var Meta
     */
    public $meta;

    /**
     * The list of the errors
     *
     * @var array[ErrorMessage]
     */
    public $errors = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->meta = \Scalr::getContainer()->api->meta;
    }
}