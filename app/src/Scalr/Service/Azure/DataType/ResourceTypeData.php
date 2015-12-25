<?php

namespace Scalr\Service\Azure\DataType;

/**
 * ResourceTypeData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class ResourceTypeData extends AbstractDataType
{
    /**
     * Specifies the type of the resource supported by the provider.
     *
     * @var string
     */
    public $resourceType;

    /**
     * Specifies the supported geo-locations of the provided resource.
     *
     * @var array
     */
    public $locations;

    /**
     * @var array
     */
    public $apiVersions;

}