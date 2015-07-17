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
        if ($config->getApiKey() !== null) {
            $requestBody = [
                'auth' => [
                    'identity' => [
                        'methods' => [
                            'token'
                        ],
                        'token' => [
                            'id' => $config->getApiKey()
                        ]
                    ]
                ]
            ];
        } else if ($config->getPassword() !== null) {
            $requestBody = [
                'auth' => [
                    'identity' => [
                        'methods' => [
                            'password'
                        ],
                        'password' => [
                            'user' => [
                                'password'  => $config->getPassword()
                            ]
                        ]
                    ]
                ]
            ];

            if ($config->getUserId()) {
                $requestBody['auth']['identity']['password']['user']['id'] = $config->getUserId();
            } else if ($config->getUsername()) {
                $requestBody['auth']['identity']['password']['user']['name'] = $config->getUsername();
            } else {
                throw new OpenStackException(
                    'Neither user name nor user id was provided for the OpenStack config.'
                );
            }
        } else {
            throw new OpenStackException(
                'Neither api key nor password was provided for the OpenStack config.'
            );
        }

        if ($config->getProjectId() !== null) {
            $requestBody['auth']['scope']['project']['id'] = $config->getProjectId();
        } else if ($config->getTenantName() !== null) {
            $requestBody['auth']['scope']['project']['name'] = $config->getTenantName();
        }

        return $requestBody;
    }
}