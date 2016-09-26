<?php
namespace Scalr\Service\Aws\Client\QueryClient;


/**
 * Amazon CloudFront Query API client.
 *
 * HTTP Query-based requests are defined as any HTTP requests using the HTTP verb GET or POST
 * and a Query parameter named either Action or Operation.
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     01.02.2013
 */
class CloudFrontQueryClient extends S3QueryClient
{
    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\Client\QueryClient\S3QueryClient::call()
     */
    public function call($action, $options, $path = '/')
    {
        $cf = $this->getAws()->cloudFront;

        $apiVersion = $cf->getCurrentApiVersion();

        if ($apiVersion >= $cf::API_VERSION_20150727) {
            $path = '/' . substr($apiVersion, 0, 4) . '-' . substr($apiVersion, 4, 2) . '-' . substr($apiVersion, 6) . '/' . ltrim($path, '/');
        }

        return parent::call($action, $options, $path);
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Client\QueryClient.S3QueryClient::getAllowedSubResources()
     */
    public static function getAllowedSubResources()
    {
        return [];
    }
}