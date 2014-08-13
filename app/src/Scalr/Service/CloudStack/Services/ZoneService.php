<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\Services\Zone\DataType\ListZonesData;
use Scalr\Service\CloudStack\Services\Zone\DataType\ZoneList;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 *
 * @method   \Scalr\Service\CloudStack\Services\Zone\V26032014\ZoneApi getApiHandler()
 *           getApiHandler()
 *           Gets an Zone API handler for the specific version
 */
class ZoneService extends AbstractService implements ServiceInterface
{

    const VERSION_26032014 = 'V26032014';

    const VERSION_DEFAULT = self::VERSION_26032014;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Services.ServiceInterface::getType()
     */
    public static function getType()
    {
        return CloudStack::SERVICE_ZONE;
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
     * Lists zones
     *
     * @param ListZonesData|array $filter Request data object
     * @param PaginationType      $pagination Pagination
     * @return ZoneList|null
     */
    public function describe($filter = null, PaginationType $pagination = null)
    {
        if ($filter !== null && !($filter instanceof ListZonesData)) {
            $filter = ListZonesData::initArray($filter);
        }
        return $this->getApiHandler()->listZones($filter, $pagination);
    }

}