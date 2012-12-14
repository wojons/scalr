<?php
namespace Scalr\Tests\Service;

use Scalr\Tests\TestCase;
use Scalr\Service\Cloudyn;

/**
 * Cloudyn api tests
 *
 * @author   Vitaliy Demidov   <zend@i.ua>
 * @since    19.11.2012
 */
class CloudynTest extends TestCase
{

	/**
	 * {@inheritdoc}
	 * @see SimpleTestCase::setUp()
	 */
	public function setUp()
	{
		parent::setUp();
	}

	/**
	 * {@inheritdoc}
	 * @see SimpleTestCase::tearDown()
	 */
	public function tearDown()
	{
		parent::tearDown();
	}

	/**
	 * @test
	 */
	public function testFunctionalServiceActions ()
	{
		if ($this->isSkipFunctionalTests()) {
			$this->markTestSkipped();
		}

		$cy = new Cloudyn('', '', isset(\CONFIG::$CLOUDYN_ENVIRONMENT) ? \CONFIG::$CLOUDYN_ENVIRONMENT : null);

		$version = $cy->getVersion();
		$this->assertNotEmpty($version);

		$res = $cy->checkStatus();
		$this->assertTrue($res);

		$countries = $cy->countries();
		$this->assertArrayHasKey('US', $countries);
	}
}