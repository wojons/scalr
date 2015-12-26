<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * SubnetData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.6.8
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\SubnetProperties  $properties
 *
 */
class SubnetData extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['properties'];

    /**
     * Specifies the supported Azure location of the NIC.
     *
     * @var string
     */
    public $name;

    /**
     * Specifies the supported Azure location of the NIC.
     *
     * @var string
     */
    public $id;

    /**
     * Specifies the supported Azure location of the NIC.
     *
     * @var string
     */
    public $etag;

    /**
     * Sets properties
     *
     * @param   array|SubnetProperties $properties
     * @return  SubnetData
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof SubnetProperties)) {
            $properties = SubnetProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

}