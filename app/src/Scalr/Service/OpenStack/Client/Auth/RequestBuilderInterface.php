<?php

namespace Scalr\Service\OpenStack\Client\Auth;

use Scalr\Service\OpenStack\OpenStackConfig;

/**
 * Interface RequestBuilderInterface
 *
 * @author N.V.
 */
interface RequestBuilderInterface
{
    /**
     * Makes auth request body
     *
     * @param  OpenStackConfig  $config The openstack config instance
     *
     * @return array            Returns the request parameters
     */
    public function makeRequest(OpenStackConfig $config);
}