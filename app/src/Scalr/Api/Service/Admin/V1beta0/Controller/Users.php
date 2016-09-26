<?php

namespace Scalr\Api\Service\Admin\V1beta0\Controller;

use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiNotImplementedErrorException;

/**
 * Admin/Users API Controller
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (09.02.2015)
 */
class Users extends ApiController
{
    /**
     * Gets a user
     *
     * @param    int    $userId  The identifier of the user
     */
    public function getAction($userId)
    {
        throw new ApiNotImplementedErrorException();
    }
}