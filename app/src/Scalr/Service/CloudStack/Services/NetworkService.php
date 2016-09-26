<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\Exception\RestClientException;
use Scalr\Service\CloudStack\Services\Network\DataType\CreateNetwork;
use Scalr\Service\CloudStack\Services\Network\DataType\ListNetworkData;
use Scalr\Service\CloudStack\Services\Network\DataType\ListNetworkOfferingsData;
use Scalr\Service\CloudStack\Services\Network\DataType\NetworkOfferingsResponseList;
use Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseData;
use Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseList;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 *
 * @method   \Scalr\Service\CloudStack\Services\Network\V26032014\NetworkApi getApiHandler()
 *           getApiHandler()
 *           Gets an Network API handler for the specific version
 */
class NetworkService extends AbstractService implements ServiceInterface
{

    const VERSION_26032014 = 'V26032014';

    const VERSION_DEFAULT = self::VERSION_26032014;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Services.ServiceInterface::getType()
     */
    public static function getType()
    {
        return CloudStack::SERVICE_NETWORK;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Services.ServiceInterface::getVersion()
     */
    public function getVersion()
    {
        return self::VERSION_DEFAULT;
    }

    /**
     * List Networks action
     *
     * Lists all available networks.
     *
     * @param   ListNetworkData|array $filter    optional The query filter.
     * @return  NetworkResponseList Returns the list of the networks or one network
     * @throws  RestClientException
     */
    public function describe($filter = null)
    {
        if ($filter !== null && !($filter instanceof ListNetworkData)) {
            $filter = ListNetworkData::initArray($filter);
        }
        return $this->getApiHandler()->listNetworks($filter);
    }

    /**
     * Creates a network
     *
     * @param   CreateNetwork|array $request    optional The query request.
     * @return NetworkResponseData
     */
    public function create($request)
    {
        if ($request !== null && !($request instanceof CreateNetwork)) {
            $request = CreateNetwork::initArray($request);
        }
        return $this->getApiHandler()->createNetwork($request);
    }

    /**
     * Deletes a network
     *
     * @param string $id the ID of the network
     * @param bool $forced the ID of the network
     * @return ResponseDeleteData
     */
    public function delete($id, $forced = false)
    {
        return $this->getApiHandler()->deleteNetwork($id, $forced);
    }

    /**
     * Reapplies all ip addresses for the particular network
     *
     * @param string $id The network this ip address should be associated to.
     * @param string $cleanup If cleanup old network elements.
     * @return NetworkResponseData
     */
    public function restart($id, $cleanup = null)
    {
        return $this->getApiHandler()->restartNetwork($id, $cleanup);
    }

    /**
     * Updates a network
     *
     * @param string $id the ID of the network
     * @param string $displayText the new display text for the network
     * @param string $name the new name for the network
     * @return NetworkResponseData
     */
    public function update($id, $displayText = null, $name = null)
    {
        return $this->getApiHandler()->updateNetwork($id, $displayText, $name);
    }

    /**
     * Lists all available network offerings.
     *
     * @param ListNetworkOfferingsData|array $filter Request data object
     * @param PaginationType $pagination Pagination
     * @return NetworkOfferingsResponseList|null
     */
    public function listOfferings($filter = null, PaginationType $pagination = null)
    {
        if ($filter !== null && !($filter instanceof ListNetworkOfferingsData)) {
            $filter = ListNetworkOfferingsData::initArray($filter);
        }
        return $this->getApiHandler()->listNetworkOfferings($filter, $pagination);
    }
}