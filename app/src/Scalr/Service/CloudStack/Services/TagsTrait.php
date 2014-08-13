<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\DataType\ResponseTagsList;
use Scalr\Service\CloudStack\DataType\ResponseTagsData;

/**
 * TagsTrait
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
trait TagsTrait
{
    /**
     * Loads ResponseTagsList from json object
     *
     * @param   object $tagsList
     * @return  ResponseTagsList Returns ResponseTagsList
     */
    protected function _loadTagsList($tagsList)
    {
        $result = new ResponseTagsList();

        if (!empty($tagsList)) {
            foreach ($tagsList as $tag) {
                $item = $this->_loadTagsData($tag);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads ResponseTagsData from json object
     *
     * @param   object $resultObject
     * @return  ResponseTagsData Returns ResponseTagsData
     */
    protected function _loadTagsData($resultObject)
    {
        $item = new ResponseTagsData();
        $properties = get_object_vars($item);

        foreach($properties as $property => $value) {
            if (property_exists($resultObject, "$property")) {
                if (is_object($resultObject->{$property})) {
                    // Fix me. Temporary fix.
                    trigger_error('Cloudstack error. Unexpected sdt object class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                    $item->{$property} = json_encode($resultObject->{$property});
                } else {
                    $item->{$property} = (string) $resultObject->{$property};
                }
            }
        }

        return $item;
    }
}