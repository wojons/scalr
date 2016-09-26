<?php


namespace Scalr\Service\OpenStack\Client\Auth;

use DateTime;
use InvalidArgumentException;
use Scalr\Service\OpenStack\Client\AuthToken;
use Scalr\Service\OpenStack\Client\ClientResponseInterface;
use Scalr\Service\OpenStack\OpenStackConfig;

/**
 * Identity Loader v3
 *
 * @author N.V.
 */
class LoaderV3 implements LoaderInterface
{

    /**
     * {@inheritdoc}
     * @see LoaderInterface::loadJson()
     */
    public static function loadJson(ClientResponseInterface $response)
    {
        $token = $response->getHeader('X-Subject-Token');
        $jsonString = $response->getContent();

        $obj = json_decode($jsonString);

        if (empty($token)) {
            $invalid = true;
        }

        if (isset($invalid) || !isset($obj->token->expires_at)) {
            throw new InvalidArgumentException("Malformed JSON document " . (string) $jsonString);
        }

        $regions = $services = [];

        if (!empty($obj->token->catalog)) {
            foreach ($obj->token->catalog as $srv) {
                foreach ($srv->endpoints as $srvEndpoint) {
                    $url = $srvEndpoint->url;

                    $srvVersion = OpenStackConfig::parseIdentityVersion($url);

                    if (isset($srvEndpoint->region)) {
                        $regions[$srvEndpoint->region] = true;
                        $endpointRegion = $srvEndpoint->region;
                    } else {
                        $endpointRegion = '';
                    }

                    if (!isset($services[$srv->type][$endpointRegion][$srvVersion])) {
                        $services[$srv->type][$endpointRegion][$srvVersion] = [];
                    }

                    $srvEndpoint->publicURL = $url;

                    //Interface - can be public, internal or admin
                    $services[$srv->type][$endpointRegion][$srvVersion][$srvEndpoint->interface] = $srvEndpoint;
                }
            }
        }
        $regions = array_keys($regions);

        $ret = new AuthToken();
        $ret->setExpires(new DateTime($obj->token->expires_at))
            ->setId($token)
            ->setAuthDocument($obj)
            ->setRegionEndpoints($services)
            ->setZones($regions)
        ;

        if (isset($obj->token->project->id)) {
            $ret->setTenantId($obj->token->project->id);
        }

        if (isset($obj->token->project->name)) {
            $ret->setTenantName($obj->token->project->name);
        }

        return $ret;
    }
}