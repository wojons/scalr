<?php
namespace Scalr\Model\Loader;

/**
 * Entity Annotation
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (06.03.2014)
 */
class Entity
{
    /**
     * Field name
     *
     * @var string
     */
    public $name;

    /**
     * @var \Scalr\Model\Mapping\Table
     */
    public $table;
}