<?php

namespace Scalr\Service\CloudStack\Exception;

use Scalr\Service\Exception\InstanceNotFound;

/**
 * Class InstanceNotFoundException
 * @author  N.V.
 */
class InstanceNotFoundException extends RestClientException implements InstanceNotFound
{
}
