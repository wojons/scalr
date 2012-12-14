<?php
namespace Scalr\Tests\Fixtures\Service\Rackspace\Services;

use Scalr\Service\Rackspace\Services\ServiceInterface;
use Scalr\Service\Rackspace\Services\AbstractService;

/**
 * Fixture FooService
 */
class FooService extends AbstractService implements ServiceInterface
{

	/**
	 * {@inheritdoc}
	 * @see Scalr\Service\Rackspace\Services.ServiceInterface::getVersion()
	 */
	public function getVersion()
	{
		return 'V2';
	}
}