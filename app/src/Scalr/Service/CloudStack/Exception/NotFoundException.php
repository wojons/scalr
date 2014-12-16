<?php

namespace Scalr\Service\CloudStack\Exception;

use Scalr\Service\Exception\NotFound;

/**
 * Class NotFoundException
 * @author  N.V.
 */
class NotFoundException extends RestClientException implements NotFound
{
}
