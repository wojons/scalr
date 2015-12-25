<?php

namespace Scalr\Service\Azure\Exception;

use Scalr\Service\Exception\InstanceNotFound;

/**
 * Class InstanceNotFoundException
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.9
 */
class InstanceNotFoundException extends RestClientException implements InstanceNotFound
{
}
