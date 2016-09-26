<?php

namespace Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes;

/**
 * Envelop for Object from Api specifications
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.11 (03.12.2015)
 */
class ObjectEntity extends ApiEntity
{
    /**
     * The list of the required fields
     *
     * @var array
     */
    public $required = [];

    /**
     * The list of the filterable fields
     *
     * @var array
     */
    public $filterable = [];

    /**
     * The list of the createOnly fields
     *
     * @var array
     */
    public $createOnly = [];

    /**
     * The list of the derived references
     *
     * @var array
     */
    public $derived = [];

    /**
     *The list of the references
     *
     * @var array
     */
    public $references = [];

    /**
     * The list of the paths enum properties
     *
     * @var array
     */
    public $concreteTypes = [];

    /**
     * This type implements real object
     *
     * @var string
     */
    public $abstractType = null;

    /**
     * This property is used for polymorphism.
     * that is used to differentiate between other schema
     *
     * @var string
     */
    public $discriminator = null;
}