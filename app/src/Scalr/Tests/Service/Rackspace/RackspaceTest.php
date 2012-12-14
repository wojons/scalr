<?php
namespace Scalr\Tests\Service\Rackspace;

use Scalr\Service\Rackspace\Rackspace;

/**
 * Rackspace TestCase
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    05.12.2012
 */
class RackspaceTest extends RackspaceTestCase
{
	public function testGetAvailableServices()
	{
		$avail = Rackspace::getAvailableServices();
		$this->assertNotEmpty($avail);
		$this->assertInternalType('array', $avail);
		$this->assertArrayHasKey('servers', $avail);
		$this->assertArrayNotHasKey('abstract', $avail);
	}

	public function testGet()
	{
		$r = new Rackspace();
		$one = $r->servers;
		$this->assertInstanceOf($this->getRackspaceClassName('Services\\ServersService'), $one);
		$two = $r->servers;
		$this->assertInstanceOf($this->getRackspaceClassName('Services\\ServersService'), $two);
		$this->assertSame($one, $two, 'Service interface is expected to be cached within each separate rackspace instance.');
	}
}