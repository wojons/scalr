<?php

namespace Scalr\Api\DataType;


/**
 * Pagination object
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (02.03.2015)
 */
class Pagination extends AbstractDataType
{
    /**
     * Link to the first page
     *
     * @var string
     */
    public $first;

    /**
     * Link to the last page
     *
     * @var string
     */
    public $last;

    /**
     * Link to the previous page
     *
     * @var string
     */
    public $prev;

    /**
     * Link to the next page
     *
     * @var string
     */
    public $next;
}