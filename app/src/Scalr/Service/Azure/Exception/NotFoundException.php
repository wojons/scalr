<?php

namespace Scalr\Service\Azure\Exception;

use Scalr\Service\Exception\NotFound;

/**
 * Class NotFoundException
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.9
 */
class NotFoundException extends RestClientException implements NotFound
{
}
