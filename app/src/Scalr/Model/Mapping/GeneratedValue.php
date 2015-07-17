<?php
namespace Scalr\Model\Mapping;

/**
 * GeneratedValue
 *
 * @since    5.0 (06.03.2014)
 * @Annotation
 * @Target({"PROPERTY"})
 */
final class GeneratedValue implements Annotation
{

    const STRATEGY_AUTO = 'AUTO';
    const STRATEGY_SEQUENCE = 'SEQUENCE';
    const STRATEGY_TABLE = 'TABLE';
    const STRATEGY_IDENTITY = 'IDENTITY';
    const STRATEGY_NONE = 'NONE';
    const STRATEGY_CUSTOM = 'CUSTOM';

    /**
     * The type of Id generator.
     *
     * @var string
     *
     * @Enum({"AUTO", "SEQUENCE", "TABLE", "IDENTITY", "NONE", "CUSTOM"})
     */
    public $strategy = 'AUTO';

    public function __invoke($strategy)
    {
        $this->strategy = strtoupper($strategy);
    }
}