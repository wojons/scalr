<?php

namespace Scalr\Service\Azure\Services\Storage\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * AccountData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Storage\DataType\AccountProperties  $properties
 *
 */
class AccountData extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['properties'];

    /**
     * The id is the URL (excluding the hostname/scheme) for this storage account.
     * See the Request URI parameters table for details on the bracketed fields.
     *
     * @var string
     */
    public $id;

    /**
     * The location of the resource. This will be one of the supported and registered Azure Geo Regions (e.g. West US, East US, Southeast Asia, etc.).
     * The geo region of a resource cannot be changed once it is created; therefore, this location parameter also cannot be changed.
     *
     * @var string
     */
    public $location;

    /**
     * The name of the storage account.
     *
     * @var string
     */
    public $name;

    /**
     * Tags array. ['key' => 'value']
     *
     * @var array
     */
    public $tags;

    /**
     * Resource Type
     * default value: Microsoft.Storage/StorageAccount
     *
     * @var string
     */
    public $type;

    /**
     * Constructor
     *
     * @param   string                    $location         Specifies the supported Azure location of the account.
     * @param   array|AccountProperties   $properties       Specifies properties
     */
    public function __construct($location, $properties)
    {
        $this->location = $location;
        $this->setProperties($properties);
    }

    /**
     * Sets properties
     *
     * @param   array|AccountProperties $properties
     * @return  AccountData
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof AccountProperties)) {
            $properties = AccountProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

}