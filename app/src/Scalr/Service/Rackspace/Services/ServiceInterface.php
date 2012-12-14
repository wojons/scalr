<?php
namespace Scalr\Service\Rackspace\Services;

/**
 * Rackspace service interface
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    04.12.2012
 */
interface ServiceInterface
{
	/**
	 * Gets a service name.
	 *
	 * Returned name must start with the lower case letter.
	 *
	 * @return  string Returns service interface name.
	 */
	public static function getName();

	/**
	 * Gets a version number
	 *
	 * @return  string Returns version of the interface
	 */
	public function getVersion();

	/**
	 * Gets an API handler for the appropriated version
	 *
	 * @return  object Returns Api handler
	 */
	public function getApiHandler();
}