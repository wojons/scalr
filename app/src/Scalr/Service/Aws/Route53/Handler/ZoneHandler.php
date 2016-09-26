<?php
namespace Scalr\Service\Aws\Route53\Handler;

use Scalr\Service\Aws\Route53Exception;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\Route53\AbstractRoute53Handler;
use Scalr\Service\Aws\DataType\MarkerType;
use Scalr\Service\Aws\Route53\DataType\ZoneList;
use Scalr\Service\Aws\Route53\DataType\ZoneData;

/**
 * ZoneHandler
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 */
class ZoneHandler extends AbstractRoute53Handler
{

    /**
     * GET Zone List action
     *
     * @param   MarkerType       $marker optional The query parameters.
     * @return  ZoneList         Returns the list of hosted zones.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function describe(MarkerType $marker = null)
    {
        return $this->getRoute53()->getApiHandler()->describeHostedZones($marker);
    }

    /**
     * GET Hosted Zone action
     *
     * @param   string           $zoneId  ID of the hosted zone.
     * @return  ZoneData         Returns hosted zone.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function fetch($zoneId)
    {
        return $this->getRoute53()->getApiHandler()->getHostedZone($zoneId);
    }

    /**
     * POST Hosted Zone action
     *
     * This action creates a new hosted zone.
     *
     * @param   ZoneData|string $config zone data object or xml document
     * @return  ZoneData Returns created hosted zone.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function create($config)
    {
        return $this->getRoute53()->getApiHandler()->createHostedZone($config);
    }

    /**
     * DELETE Hosted Zone action
     *
     * @param   string                        $zoneId ID of the hosted zone.
     * @return  bool|ChangeData               Returns ChangeData on success.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function delete($zoneId)
    {
        return $this->getRoute53()->getApiHandler()->deleteHostedZone($zoneId);
    }
}