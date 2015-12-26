<?php

namespace Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes;

/**
 * Result  object
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.6.14 (03.12.2015)
 */
class DetailResponse extends AbstractSpecObject
{
    /**
     * Meta property object
     *
     * @var Property
     */
    public $meta;

    /**
     * Result data property object
     *
     * @var Property
     */
    public $data;

    /**
     * Errors property object
     *
     * @var Property
     */
    public $errors;

    /**
     * Warnings property object
     *
     * @var Property
     */
    public $warnings;

    /**
     * Return object entity
     *
     * @return ApiEntity|ObjectEntity
     */
    public function getObjectEntity()
    {
        return $this->data->entity;
    }
}