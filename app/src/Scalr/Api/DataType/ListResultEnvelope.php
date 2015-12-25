<?php

namespace Scalr\Api\DataType;


/**
 * ListResultEnvelope object
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (05.03.2015)
 */
class ListResultEnvelope extends AbstractDataType
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
     * The pagination
     *
     * @var  Pagination
     */
    public $pagination;

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