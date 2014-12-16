<?php

namespace Scalr\Service\OpenStack\Exception;

use Scalr\Service\Exception\InstanceNotFound;

/**
 * Class InstanceNotFoundException
 * @author  N.V.
 */
class InstanceNotFoundException extends RestClientException implements InstanceNotFound
{
}
