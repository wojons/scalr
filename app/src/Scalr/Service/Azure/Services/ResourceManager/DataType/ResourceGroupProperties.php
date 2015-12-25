<?php

namespace Scalr\Service\Azure\Services\ResourceManager\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * ResourceGroupProperties
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class ResourceGroupProperties extends AbstractDataType
{
    /**
     * Specifies the current provisioning state of the resource group.
     * When a resource group is successfully created, the value of the element is Succeeded.
     * If a template deployment is being created, this value can change depending on the status of the deployment.
     *
     * @var string
     */
    public $provisioningState;

}