<?php

namespace Scalr\Service\Aws\Exception;

use Scalr\Service\Exception\InstanceNotFound;
use Scalr\Service\Aws\Client\QueryClientException;

/**
 * Class InstanceNotFoundException
 *
 * @author  N.V.
 */
class InstanceNotFoundException extends QueryClientException implements InstanceNotFound
{
}
