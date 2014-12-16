<?php

namespace Scalr\Modules\Platforms\GoogleCE\Exception;

use Exception;
use Scalr\Service\Exception\InstanceNotFound;

/**
 * Class InstanceNotFoundException
 * @author  N.V.
 */
class InstanceNotFoundException extends Exception implements InstanceNotFound
{
}
