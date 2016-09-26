<?php

namespace Scalr\Service\OpenStack\Client\Auth;

use Scalr\Service\OpenStack\OpenStackConfig;
use Scalr\Service\OpenStack\Client\ClientResponseInterface;
use Scalr\Exception\NotSupportedException;

/**
 * Class LoaderFactory
 *
 * @author N.V.
 */
class LoaderFactory
{

    /**
     * Creates token from the response
     *
     * @param   ClientResponseInterface $response The response instance
     * @param   OpenStackConfig         $config   The openstack config
     *
     * @return \Scalr\Service\OpenStack\Client\AuthToken
     *
     * @throws  NotSupportedException
     */
    public static function makeToken(ClientResponseInterface $response, OpenStackConfig $config = null)
    {
        $version = $config === null ? 2 : $config->getIdentityVersion();

        switch ($version) {
            case 2:
                return LoaderV2::loadJson($response);
            case 3:
                return LoaderV3::loadJson($response);
            default:
                throw new NotSupportedException("OpenStack API v{$version} is not supported!");
        }
    }
}