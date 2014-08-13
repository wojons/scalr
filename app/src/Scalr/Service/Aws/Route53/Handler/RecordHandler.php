<?php
namespace Scalr\Service\Aws\Route53\Handler;

use Scalr\Service\Aws\Route53Exception;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\Route53\AbstractRoute53Handler;
use Scalr\Service\Aws\DataType\MarkerType;
use Scalr\Service\Aws\Route53\DataType\ChangeRecordSetsRequestData;
use Scalr\Service\Aws\Route53\DataType\ChangeData;
use Scalr\Service\Aws\Route53\DataType\RecordList;

/**
 * RecordHandler
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 */
class RecordHandler extends AbstractRoute53Handler
{

    /**
     * POST Resource Record action
     *
     * This action creates a new hosted zone.
     *
     * @param   string                             $zoneId  hosted zone id
     * @param   ChangeRecordSetsRequestData|string $config  request data object or XML document
     * @return  ChangeData Returns change data.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function update($zoneId, $config)
    {
        return $this->getRoute53()->getApiHandler()->updateRecordSets($zoneId, $config);
    }

    /**
     * GET Record Sets List action
     *
     * @param   string       $zoneId required parameter.
     * @param   string       $name optional query parameter.
     * @param   string       $type optional query parameter.
     * @param   MarkerType   $marker optional The query parameters.
     * @return  RecordList   Returns the list of record sets.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function describe($zoneId, $name = null, $type = null, MarkerType $marker = null)
    {
        return $this->getRoute53()->getApiHandler()->describeRecordSets($zoneId, $name, $type, $marker);
    }

    /**
     * GET Change action
     *
     * @param   string           $changeId  ID of the change data.
     * @return  ChangeData       Returns change data.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function fetch($changeId)
    {
        return $this->getRoute53()->getApiHandler()->getChange($changeId);
    }
}