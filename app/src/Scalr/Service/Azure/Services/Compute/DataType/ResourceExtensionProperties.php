<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * ResourceExtensionProperties
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\ResourceExtensionSettings  $settings
 *
 */
class ResourceExtensionProperties extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['settings'];

    /**
     * Specifies name of the extension’s publisher.
     *
     * @var string
     */
    public $publisher;

    /**
     * Specifies type of extension.
     *
     * @var string
     */
    public $type;

    /**
     * Specifies version of the extension.
     *
     * @var string
     */
    public $typeHandlerVersion;

    /**
     * Constructor
     *
     * @param   string  $publisher           Specifies name of the extension’s publisher.
     * @param   string  $type                Specifies type of extension. exists
     * @param   string  $typeHandlerVersion  Specifies version of the extension.
     */
    public function __construct($publisher, $type, $typeHandlerVersion)
    {
        $this->publisher = $publisher;
        $this->type = $type;
        $this->typeHandlerVersion = $typeHandlerVersion;
    }

    /**
     * Sets settings
     *
     * @param   array|ResourceExtensionSettings $settings
     * @return  ResourceExtensionProperties
     */
    public function setSettings($settings = null)
    {
        if (!($settings instanceof ResourceExtensionSettings)) {
            $settings = ResourceExtensionSettings::initArray($settings);
        }

        return $this->__call(__FUNCTION__, [$settings]);
    }

}