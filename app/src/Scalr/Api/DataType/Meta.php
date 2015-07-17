<?php

namespace Scalr\Api\DataType;


/**
 * Meta object
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (05.03.2015)
 */
class Meta extends \stdClass
{
    /**
     * The unique identifier of the request (UUID)
     * @var string
     */
    public $requestId;

    /**
     * Constuctor
     */
    public function __construct()
    {
        $this->requestId = \Scalr::GenerateUID();
    }
}