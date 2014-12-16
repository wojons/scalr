<?php

namespace Scalr\Service\Cloud\Rackspace\Exception;

use Scalr\Service\Exception\InstanceNotFound;

/**
 * InstanceNotFoundException class
 * @author  N.V.
 */
class InstanceNotFoundException extends ClientException implements InstanceNotFound
{
}
