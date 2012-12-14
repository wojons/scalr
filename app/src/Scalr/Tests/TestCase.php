<?php
namespace Scalr\Tests;


/**
 * Basic TestCase class
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     03.12.2012
 */
class TestCase extends \PHPUnit_Framework_TestCase
{

	/**
	 * Returns true if functional tests should be skipped.
	 *
	 * @return  bool Returns true if functional tests should be skipped.
	 */
	public function isSkipFunctionalTests()
	{
		return isset(\CONFIG::$PHPUNIT_SKIP_FUNCTIONAL_TESTS) && \CONFIG::$PHPUNIT_SKIP_FUNCTIONAL_TESTS ||
		       !isset(\CONFIG::$PHPUNIT_SKIP_FUNCTIONAL_TESTS) ;
	}

	/**
	 * Returns fixtures directory
	 *
	 * @return string Returns fixtures directory
	 */
	public function getFixturesDirectory()
	{
		return __DIR__ . '/Fixtures';
	}
}