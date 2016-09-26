<?php
namespace Scalr\Tests\Model\Entity;

use Scalr\Tests\TestCase;
use Scalr\Model\Entity\Script;

/**
 * ScriptTest
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.4.0 (25.02.2015)
 */
class ScriptTest extends TestCase
{
    /**
     * @test
     * @functional
     */
    public function testGetVersions()
    {
        $list = Script::find(null, null, null, 10);

        $this->assertInternalType('array', $list->getArrayCopy());
        $this->assertInternalType('integer', count($list));
        $this->assertInternalType('integer', $list->count());

        try {
            /* @var $script Script */
            $script = Script::findOne([]);
        } catch (\Exception $e) {
            $this->markTestSkipped($e->getMessage());
        }

        if (!$script) {
            $this->markTestSkipped("There are no scripts so it can not proceed");
        }

        $versions = $script->getVersions()->getArrayCopy();

        $this->assertInternalType('array', $versions);
    }
}