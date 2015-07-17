<?php


namespace Scalr\Service\OpenStack\Client\Auth;


use Scalr\Service\OpenStack\Exception\OpenStackException;
use Scalr\Service\OpenStack\OpenStackConfig;

class RequestBuilderV2 implements RequestBuilderInterface
{

    /**
     * {@inheritdoc}
     * @see RequestInterface::makeRequest()
     */
    public function makeRequest(OpenStackConfig $config)
    {
        if ($config->getApiKey() !== null) {
            $requestBody = [
                'auth' => [
                    "RAX-KSKEY:apiKeyCredentials" => [
                        'username' => $config->getUsername(),
                        'apiKey'   => $config->getApiKey(),
                    ]
                ]
            ];
        } else if ($config->getPassword() !== null) {
            $requestBody = [
                'auth' => [
                    "passwordCredentials" => [
                        'username'  => $config->getUsername(),
                        'password'  => $config->getPassword(),
                    ]
                ]
            ];
        } else {
            throw new OpenStackException(
                'Neither api key nor password was provided for the OpenStack config.'
            );
        }

        if ($config->getTenantName() !== null) {
            $requestBody['auth']['tenantName'] = $config->getTenantName();
        }

        return $requestBody;
    }
}