<?php
namespace Scalr\Tests\Service\Rackspace;

use Scalr\Tests\TestCase;

/**
 * Rackspace TestCase
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    05.12.2012
 */
class RackspaceTestCase extends TestCase
{
	/**
	 * {@inheritdoc}
	 * @see Scalr\Tests.TestCase::getFixturesDirectory()
	 */
	public function getFixturesDirectory()
	{
		return parent::getFixturesDirectory() . '/Service/Rackspace';
	}

	/**
	 * Gets full class name by its suffix after Rackspace\\
	 *
	 * @param   string   $classSuffix
	 * @return  string
	 */
	public function getRackspaceClassName($classSuffix)
	{
		return 'Scalr\\Service\\Rackspace\\' . $classSuffix;
	}

	/**
	 * Gets full FIXTURE class name  by its suffix after Rackspace\\
	 *
	 * @param   string   $classSuffix
	 * @return  string
	 */
	public function getRackspaceFixtureClassName($classSuffix)
	{
		return 'Scalr\\Tests\\Fixtures\\Service\\Rackspace\\' . $classSuffix;
	}
}