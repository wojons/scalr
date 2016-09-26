<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * OfferData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class OfferData extends AbstractDataType
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $location;

}