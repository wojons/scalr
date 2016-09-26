<?php


namespace Scalr\Service\OpenStack\Client\Auth;


use Scalr\Service\OpenStack\Exception\OpenStackException;
use Scalr\Service\OpenStack\OpenStackConfig;

class RequestBuilderV3 implements RequestBuilderInterface
{

    /**
     * {@inheritdoc}
     * @see RequestInterface::makeRequest()
     */
    public function makeRequest(OpenStackConfig $config)
    {
        if (!empty($config->getApiKey())) {
            $requestBody = [
                'auth' => [
                    'identity' => [
                        'methods' => ['token'],
                        'token'   => ['id' => $config->getApiKey()]
                    ]
                ]
            ];
        } else if (!empty($config->getPassword())) {
            $requestBody = [
                'auth' => [
                    'identity' => [
                        'methods'  => ['password'],
                        'password' => ['user' => ['password'  => $config->getPassword()]]
                    ]
                ]
            ];

            if ($config->getUserId()) {
                $requestBody['auth']['identity']['password']['user']['id'] = $config->getUserId();
            } else if ($config->getUsername()) {
                $requestBody['auth']['identity']['password']['user']['name'] = $config->getUsername();
            } else {
                throw new OpenStackException(
                    'Neither user name nor user identifier was provided for the OpenStack config.'
                );
            }

            if ($config->getDomainName()) {
                $requestBody['auth']['identity']['password']['user']['domain']['name'] = $config->getDomainName();
            }

        } else {
            throw new OpenStackException(
                'Neither api key nor password was provided for the OpenStack config.'
            );
        }

        if ($config->getProjectId() !== null) {
            $requestBody['auth']['scope']['project']['id'] = $config->getProjectId();

            if ($config->getDomainName()) {
                $requestBody['auth']['scope']['project']['domain']['name'] = $config->getDomainName();
            }
        } else if ($config->getTenantName() !== null) {
            $requestBody['auth']['scope']['project']['name'] = $config->getTenantName();

            if ($config->getDomainName()) {
                $requestBody['auth']['scope']['project']['domain']['name'] = $config->getDomainName();
            }
        }

        return $requestBody;
    }
}