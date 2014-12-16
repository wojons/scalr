<?php

namespace Scalr\Service\OpenStack\Exception;

use Scalr\Service\Exception\NotFound;

/**
 * Class NotFoundException
 * @author  N.V.
 */
class NotFoundException extends RestClientException implements NotFound
{
}
