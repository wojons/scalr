<?php
namespace Scalr\Model\Mapping;

/**
 * UniqueConstraint
 *
 * @since       5.0 (06.03.2014)
 * @Annotation
 * @Target("ANNOTATION")
 */
final class UniqueConstraint implements Annotation
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var array<string>
     */
    public $columns;
}