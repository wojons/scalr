<?php
namespace Scalr\Model\Mapping;

/**
 * Index
 *
 * @since       5.0 (06.03.2014)
 * @Annotation
 * @Target("ANNOTATION")
 */
final class Index implements Annotation
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