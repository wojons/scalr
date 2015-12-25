<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * VirtualInstanceViewData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class StatusData extends AbstractDataType
{
    /**
     * @var string
     */
    public $code;

    /**
     * @var string
     */
    public $level;

    /**
     * @var string
     */
    public $displayStatus;

    /**
     * @var string
     */
    public $message;

    /**
     * @var string
     */
    public $time;

}