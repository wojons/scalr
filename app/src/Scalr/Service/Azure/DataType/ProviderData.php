<?php

namespace Scalr\Service\Azure\DataType;

/**
 * ProviderData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 *
 */
class ProviderData extends GeoLocationData
{
    const REGISTRATION_STATE_REGISTERED = 'Registered';

    const REGISTRATION_STATE_NOT_REGISTERED = 'NotRegistered';

    /**
     * Sets resourceTypes
     *
     * @param   array|ResourceTypeList $resourceTypes
     * @return  ProviderData
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

    /**
     * Gets providers that should be registered
     *
     * @return array
     */
    public static function getRequiredProviders()
    {
        return [
            self::RESOURCE_PROVIDER_AUTHORIZATION, self::RESOURCE_PROVIDER_COMPUTE,
            self::RESOURCE_PROVIDER_NETWORK, self::RESOURCE_PROVIDER_RESOURCES, self::RESOURCE_PROVIDER_STORAGE
        ];
    }

}