<?php

namespace Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes;

/**
 * Envelope for each property in Api specifications
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.11 (03.12.2015)
 */
class Property extends AbstractSpecObject
{
    /**
     * @var bool
     */
    public $readOnly = false;

    /**
     * @var string
     */
    public $type = null;

    /**
     * @var null
     */
    public $description = null;
}