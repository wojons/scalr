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

        $services = array();
        $regions = array();
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
                        $services[$srv->type][$endpointRegion][$srvVersion] = array();
                    }

                    $srvEndpoint->publicURL = $url;

                    $services[$srv->type][$endpointRegion][$srvVersion][] = $srvEndpoint;
                }
            }
        }
        $regions = array_keys($regions);

        $ret = new AuthToken();
        $ret
            ->setExpires(new DateTime($obj->token->expires_at))
            ->setId($token)
            ->setAuthDocument($obj)
            ->setRegionEndpoints($services)
            ->setZones($regions)
        ;
        if (isset($obj->token->user->id)) {
            $ret->setTenantId($obj->token->user->id);
        }
        if (isset($obj->token->user->name)) {
            $ret->setTenantName($obj->token->user->name);
        }

        return $ret;
    }
}