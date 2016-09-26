<?php

namespace Scalr\Service\OpenStack\Client\Auth;

use DateTime;
use InvalidArgumentException;
use Scalr\Service\OpenStack\Client\AuthToken;
use Scalr\Service\OpenStack\Client\ClientResponseInterface;

/**
 * Identity Loader v2
 *
 * @author N.V.
 */
class LoaderV2 implements LoaderInterface
{

    /**
     * {@inheritdoc}
     * @see LoaderInterface::loadJson()
     */
    public static function loadJson(ClientResponseInterface $response)
    {
        $jsonString = $response->getContent();

        $obj = json_decode($jsonString);

        if (!isset($obj->access->token)) {
            $invalid = true;
        }
        if (isset($invalid) || !isset($obj->access->token->expires) || !isset($obj->access->token->id)) {
            throw new InvalidArgumentException("Malformed JSON document " . (string) $jsonString);
        }

        $services = array();
        $regions = array();
        if (!empty($obj->access->serviceCatalog)) {
            foreach ($obj->access->serviceCatalog as $srv) {
                foreach ($srv->endpoints as $srvEndpoint) {
                    $srvVersion = isset($srvEndpoint->versionId) ? $srvEndpoint->versionId . '' : '';
                    if (isset($srvEndpoint->region)) {
                        $regions[$srvEndpoint->region] = true;
                        $endpointRegion = $srvEndpoint->region;
                    } else {
                        $endpointRegion = '';
                    }
                    if (!isset($services[$srv->type][$endpointRegion][$srvVersion])) {
                        $services[$srv->type][$endpointRegion][$srvVersion] = array();
                    }
                    $services[$srv->type][$endpointRegion][$srvVersion][] = $srvEndpoint;
                }
            }
        }
        $regions = array_keys($regions);

        $ret = new AuthToken();
        $ret
            ->setExpires(new DateTime($obj->access->token->expires))
            ->setId($obj->access->token->id)
            ->setAuthDocument($obj)
            ->setRegionEndpoints($services)
            ->setZones($regions)
        ;
        if (isset($obj->access->token->tenant->id)) {
            $ret->setTenantId($obj->access->token->tenant->id);
        }
        if (isset($obj->access->token->tenant->name)) {
            $ret->setTenantName($obj->access->token->tenant->name);
        }

        return $ret;
    }
}