<?php

namespace Scalr\Api\DataType;

/**
 * ResultEnvelope object
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (02.03.2015)
 */
class ResultEnvelope extends AbstractDataType
{
    /**
     * Meta object
     *
     * @var Meta
     */
    public $meta;

    /**
     * Result data
     *
     * @var mixed
     */
    public $data;

    /**
     * Warnings object
     *
     * @var Warnings
     */
    public $warnings;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->meta     = \Scalr::getContainer()->api->meta;
        $this->warnings = \Scalr::getContainer()->api->warnings;
    }
}