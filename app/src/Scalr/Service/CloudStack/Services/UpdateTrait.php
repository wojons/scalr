<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\DataType\ResponseDeleteData;

/**
 * UpdateTrait
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
trait UpdateTrait
{
    /**
     * Loads ResponseDeleteData from json object
     *
     * @param   object $resultObject
     * @return  ResponseDeleteData Returns ResponseDeleteData
     */
    protected function _loadUpdateData($resultObject)
    {
        $item = new ResponseDeleteData();
        $item->displaytext = (property_exists($resultObject, 'displaytext') ? (string) $resultObject->displaytext : null);
        $item->success = (property_exists($resultObject, 'value') ? (bool) $resultObject->success : null);

        return $item;
    }
}