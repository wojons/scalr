<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

/**
 * SecurityGroupData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.9
 *
 */
class SecurityGroupData extends CreateSecurityGroup
{
    /**
     * The identifying URL of the Network Security Group.
     *
     * @var string
     */
    public $id;

    /**
     * The name of the Network Security Group.
     *
     * @var string
     */
    public $name;

    /**
     * System generated meta-data enabling concurrency control
     *
     * @var string
     */
    public $etag;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Sets properties
     *
     * @param   array|SecurityGroupProperties $properties
     * @return  SecurityGroupData
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof SecurityGroupProperties)) {
            $properties = SecurityGroupProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

}