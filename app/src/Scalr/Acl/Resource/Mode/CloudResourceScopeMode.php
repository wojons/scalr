<?php

namespace Scalr\Acl\Resource\Mode;

use Scalr\Acl\Resource\ModeInterface;
use JsonSerializable;
use Scalr\Acl\Resource\ModeDescription;

/**
 * CloudObjectScopeMode
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    31.08.2015
 */
class CloudResourceScopeMode implements ModeInterface, JsonSerializable
{
    /**
     * Access to all objects on the Cloud (1)
     */
    const MODE_ALL = 0b00001;

    /**
     * Access only to objects from current Environment (4)
     */
    const MODE_ENVIRONMENT = 0b00100;

    /**
     * Access only to objects which correspond to Farms to which user has access. (16)
     */
    const MODE_MANAGED_FARMS = 0b10000;

    /**
     * Mapping
     *
     * @var ModeDescritpion[]
     */
    private static $mapping;

    /**
     * The name of the Cloud Resource in the plural form
     *
     * @var  string
     */
    protected $resourceNamePlural = 'objects';

    /**
     * Resource Identifier the Mode corresponds to
     *
     * @var  int
     */
    protected $resourceId;

    /**
     * Constructor
     *
     * @param  string   $resourceId         ACL Resource identifier the Mode corresponds to
     * @param  string   $resourceNamePlural The name of the Cloud Resource in the plural form (volumes, snapshots etc...)
     */
    public function __construct($resourceId, $resourceNamePlural)
    {
        $this->resourceId = $resourceId;
        $this->resourceNamePlural = $resourceNamePlural;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Acl\Resource\ModeInterface::getResourceId()
     */
    public function getResourceId()
    {
        return $this->resourceId;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Acl\Resource\ModeInterface::getDefaultValue()
     */
    public function getDefault()
    {
        return static::MODE_ALL;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Acl\Resource\ModeInterface::getMapping()
     */
    public function getMapping()
    {
        if (empty(static::$mapping[$this->resourceId])) {
            static::$mapping[$this->resourceId] = [
                static::MODE_ALL           => new ModeDescription('ALL', 'All', 'Allows access to all Cloud ' . $this->resourceNamePlural . '.'),
                static::MODE_ENVIRONMENT   => new ModeDescription('ENVIRONMENT', 'Any Farm', 'Allows access only to ' . $this->resourceNamePlural . ' from current Environment.'),
                static::MODE_MANAGED_FARMS => new ModeDescription('MANAGED_FARMS', 'Farms user has access to', 'Allows access only to ' . $this->resourceNamePlural . ' which correspond to Farms to which user has access in current Environment.'),
            ];
        }

        return static::$mapping[$this->resourceId];
    }

    /**
     * {@inheritdoc}
     * @see JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        $arr = [];

        $default = $this->getDefault();

        foreach ($this->getMapping() as $key => $desc) {
            $data = get_object_vars($desc);
            $data['key'] = $key;

            if ($key === $default) {
                $data['default'] = true;
            }

            $arr[] = $data;
        }

        return $arr;
    }
}