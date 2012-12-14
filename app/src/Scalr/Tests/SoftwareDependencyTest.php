<?php
namespace Scalr\Tests;

/**
 * Software dependency test
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     30.10.2012
 */
class SoftwareDependencyTest extends TestCase
{
	/**
	 * {@inheritdoc}
	 * @see PHPUnit_Framework_TestCase::setUp()
	 */
	public function setUp()
	{
		parent::setUp();
	}

	/**
	 * {@inheritdoc}
	 * @see PHPUnit_Framework_TestCase::tearDown()
	 */
	public function tearDown()
	{
		parent::tearDown();
	}

	/**
	 * Here we should add assertions for all php dependencies which is usded by Scalr.
	 *
	 * @test
	 */
	public function testDependencies()
	{
		$this->assertTrue(
			version_compare('5.3.6', phpversion(), 'le'),
			sprintf('You have %s PHP version. It must be greater than or equal 5.3.6', phpversion())
		);

		$this->assertTrue(
			class_exists('HttpRequest'),
		    'Http extension is required for the application. '
		  . 'Please install it http://www.php.net/manual/en/http.install.php'
		);

		$this->assertTrue(
			class_exists('Mongo'),
		    'Mongo extension is required for the application. '
		  . 'Please install it http://www.php.net/manual/en/mongo.installation.php'
		);

		$this->assertTrue(
			version_compare('1.2.12', phpversion('mongo'), 'le'),
			'Version of mongodb driver must be greater than or equal 1.2.12'
		);

		//Please add assertions here
	}
}