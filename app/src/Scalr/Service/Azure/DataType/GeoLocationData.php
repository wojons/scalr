<?php

namespace Scalr\Service\Azure\DataType;

/**
 * GeoLocationData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\DataType\ResourceTypeList  $resourceTypes
 *            Specifies the types supported by the provider.
 *
 */
class GeoLocationData extends AbstractDataType
{
    const RESOURCE_PROVIDER_COMPUTE = 'Microsoft.Compute';

    const RESOURCE_PROVIDER_NETWORK = 'Microsoft.Network';

    const RESOURCE_PROVIDER_RESOURCES = 'Microsoft.Resources';

    const RESOURCE_PROVIDER_AUTHORIZATION = 'Microsoft.Authorization';

    const RESOURCE_PROVIDER_STORAGE = 'Microsoft.Storage';

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['resourceTypes'];

    /**
     * Specifies the identifying URL of the resource provider.
     *
     * @var string
     */
    public $id;

    /**
     * Specifies the namespace of the resource provider.
     *
     * @var string
     */
    public $namespace;

    /**
     * Possible values are: NotRegistered|Registering|Registered|Unregistering|Unregistered
     *
     * @var string
     */
    public $registrationState;

    /**
     * Sets resourceTypes
     *
     * @param   array|ResourceTypeList $resourceTypes
     * @return  GeoLocationData
     */
    public function setResourceTypes($resourceTypes = null)
    {
        if (!($resourceTypes instanceof ResourceTypeList)) {
            $resourceTypeList = new ResourceTypeList();

            foreach ($resourceTypes as $resourceType) {
                if (!($resourceType instanceof ResourceTypeData)) {
                    $resourceTypeData = ResourceTypeData::initArray($resourceType);
                } else {
                    $resourceTypeData = $resourceType;
                }

                $resourceTypeList->append($resourceTypeData);
            }
        } else {
            $resourceTypeList = $resourceTypes;
        }

        return $this->__call(__FUNCTION__, [$resourceTypeList]);
    }

}