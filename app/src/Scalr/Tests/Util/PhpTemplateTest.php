<?php

namespace Scalr\Tests\Util;

use Scalr\Util\PhpTemplate;
use Scalr\Tests\TestCase;

/**
 * PhpTemplateTest
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    30.09.2013
 */
class PhpTemplateTest extends TestCase
{
    /**
     * {@inheritdoc}
     * @see Scalr\Tests.TestCase::getFixturesDirectory()
     */
    public function getFixturesDirectory()
    {
        return parent::getFixturesDirectory() . '/Util';
    }

    /**
     * Gets fixture
     *
     * @param   string   $file  The file
     * @return  string   Returns full path to fixture
     */
    public function getFixturePath($file)
    {
        return $this->getFixturesDirectory() . '/' . $file;
    }

    /**
     * @test
     */
    public function testParse()
    {
        $result = PhpTemplate::load($this->getFixturePath('tpl.php'), array('verb' => 'do', 'b' => 'better'));
        $this->assertEquals('John do this better', $result);
    }
}