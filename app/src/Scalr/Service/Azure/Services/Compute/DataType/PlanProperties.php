<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * PlanProperties
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class PlanProperties extends AbstractDataType
{
    /**
     * Specifies name of the image from the marketplace.
     *
     * @var string
     */
    public $name;

    /**
     * Specifies publisher of the image from the marketplace.
     *
     * @var string
     */
    public $publisher;

    /**
     * Specifies product of the image from the marketplace.
     *
     * @var string
     */
    public $product;

    /**
     * Constructor
     *
     * @param   string     $name       Specifies name of the image from the marketplace.
     * @param   string     $publisher  Specifies publisher of the image from the marketplace.
     * @param   string     $product    Specifies product of the image from the marketplace.
     */
    public function __construct($name, $publisher, $product)
    {
        $this->name      = $name;
        $this->publisher = $publisher;
        $this->product   = $product;
    }

}