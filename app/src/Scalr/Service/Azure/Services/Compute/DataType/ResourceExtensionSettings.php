<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * ResourceExtensionSettings
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class ResourceExtensionSettings extends AbstractDataType
{
    /**
     * Specifies the script file path. Format: ['value']
     *
     * @var array
     */
    public $fileUris;

    /**
     * Specifies command used to execute the script.
     *
     * @var string
     */
    public $commandToExecute;
}