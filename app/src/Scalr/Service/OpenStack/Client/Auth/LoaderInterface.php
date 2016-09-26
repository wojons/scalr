<?php

namespace Scalr\Service\OpenStack\Client\Auth;

use Scalr\Service\OpenStack\Client\AuthToken;
use Scalr\Service\OpenStack\Client\ClientResponseInterface;

/**
 * Identity Loader Interface
 *
 * @author N.V.
 */
interface LoaderInterface
{

    /**
     * Creates new instance of the AuthToken
     *
     * @param   ClientResponseInterface $response Response received from authenticate request
     * @return  AuthToken
     */
    public static function loadJson(ClientResponseInterface $response);
}