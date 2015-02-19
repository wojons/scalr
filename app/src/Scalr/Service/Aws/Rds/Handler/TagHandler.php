<?php
namespace Scalr\Service\Aws\Rds\Handler;

use Scalr\Service\Aws\DataType\ListDataType;
use Scalr\Service\Aws\Rds\DataType\TagsList;
use Scalr\Service\Aws\RdsException;
use Scalr\Service\Aws\Rds\AbstractRdsHandler;

/**
 * Amazon RDS TagHandler
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     29.01.2015
 */
class TagHandler extends AbstractRdsHandler
{
    /**
     * TagHandler action
     * Lists all tags on an Amazon RDS resource.
     *
     * @param string $resourceName  Resource identifier for the Amazon RDS resource.
     * @param string $resourceType  The type of Amazon RDS resource (db|es|og|pg|ri|secgrp|snapshot|subgrp)
     * @return TagsList
     * @throws RdsException
     */
    public function describe($resourceName, $resourceType)
    {
        return $this->getRds()->getApiHandler()->listTagsForResource($resourceName, $resourceType);
    }

    /**
     * TagHandler action
     * Adds metadata tags to an Amazon RDS resource.
     *
     * @param string   $resourceName    Resource identifier for the Amazon RDS resource.
     * @param string   $resourceType    The type of Amazon RDS resource (db|es|og|pg|ri|secgrp|snapshot|subgrp)
     * @param array|TagsList $tagsList  List of tags to add
     * @return array    Returns array of added tags
     * @throws RdsException
     */
    public function add($resourceName, $resourceType, $tagsList)
    {
        if ($tagsList !== null && !($tagsList instanceof TagsList)) {
            $tagsList = new TagsList($tagsList);
        }

        return $this->getRds()->getApiHandler()->addTagsToResource($resourceName, $resourceType, $tagsList);
    }

    /**
     * TagHandler action
     * Removes metadata tags from an Amazon RDS resource.
     *
     * @param string           $resourceName  Resource identifier for the Amazon RDS resource.
     * @param string           $resourceType  The type of Amazon RDS resource (db|es|og|pg|ri|secgrp|snapshot|subgrp)
     * @param array|ListDataType  $tagsKeys      Array of tag keys to remove
     * @return bool
     * @throws RdsException
     */
    public function remove($resourceName, $resourceType, $tagsKeys)
    {
        if ($tagsKeys !== null && !($tagsKeys instanceof ListDataType)) {
            $tagsKeys = new ListDataType($tagsKeys, 'TagKeys');
        }

        return $this->getRds()->getApiHandler()->removeTagsFromResource($resourceName, $resourceType, $tagsKeys);
    }

}