<?php
namespace Scalr\Model\Mapping;

/**
 * Table
 *
 * @since       5.0 (06.03.2014)
 * @Annotation
 * @Target("CLASS")
 */
final class Table implements Annotation
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $schema;

    /**
     * @var string
     */
    public $service = 'adodb';

    /**
     * @var array<\Scalr\Model\Mapping\Index>
     */
    public $indexes;

    /**
     * @var array<\Scalr\Model\Mapping\UniqueConstraint>
     */
    public $uniqueConstraints;

    /**
     * @var array
     */
    public $options = array();
}