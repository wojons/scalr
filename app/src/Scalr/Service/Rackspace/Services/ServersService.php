<?php
namespace Scalr\Service\Rackspace\Services;

/**
 * Rackspace Next Generation Cloud Servers™ service interface
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    04.12.2012
 */
class ServersService extends AbstractService implements ServiceInterface
{

	const VERSION_V2 = 'V2';

	const VERSION_DEFAULT = self::VERSION_V2;

	/**
	 * {@inheritdoc}
	 * @see Scalr\Service\Rackspace\Services.ServiceInterface::getVersion()
	 */
	public function getVersion()
	{
		return self::VERSION_DEFAULT;
	}
}