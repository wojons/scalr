<?php

namespace Scalr\Tests\Fixtures\Util\Api;

use Scalr\System\Config\Yaml;
use Scalr\Util\Api\SpecMutator;

/**
 * Mutator Test
 *
 * @author N.V.
 */
class TestMutator extends SpecMutator
{

    private $modifications;

    public function __construct(array $modifications)
    {
        $this->modifications = $modifications;
    }

    /**
     * {@inheritdoc}
     * @see SpecMutator::apply()
     */
    public function apply(Yaml $config, $version)
    {
        foreach ($this->modifications as $modification) {
            call_user_func_array([$this, 'removeItem'], $modification);
        }
    }
}