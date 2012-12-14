<?php
namespace Scalr\Tests\Fixtures\Service\Rackspace\Services;

use Scalr\Service\Rackspace\Services\ServiceInterface;
use Scalr\Service\Rackspace\Services\AbstractService;

/**
 * Fixture FakeService
 */
class FakeService extends AbstractService implements ServiceInterface
{

	public static function getName()
	{
		return 'redefined';
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Service\Rackspace\Services.ServiceInterface::getVersion()
	 */
	public function getVersion()
	{
		return 'V2';
	}
}