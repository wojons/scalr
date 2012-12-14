<?php
namespace Scalr\Tests\Service\Rackspace\Services;

use Scalr\Tests\Service\Rackspace\RackspaceTestCase;

/**
 * AbstractServiceTest
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    05.12.2012
 */
class AbstractServiceTest extends RackspaceTestCase
{

	/**
	 * Data provider
	 */
	public function provider()
	{
		return array(
			array($this->getRackspaceFixtureClassName('Services\\FooService'), 'foo'),
			array($this->getRackspaceFixtureClassName('Services\\SomeService'), 'some'),
			array($this->getRackspaceFixtureClassName('Services\\FakeService'), 'redefined'),
		);
	}

	/**
	 * @test
	 * @dataProvider provider
	 *
	 * @param   string  $class A service interface class
	 * @param   string  $name  An expected service name
	 */
	public function testGetName($class, $name)
	{
		$this->assertNotNull($class::getName());
		$this->assertEquals($name, $class::getName());
	}
}