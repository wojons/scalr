<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\DataType\TemplateResponseList;
use Scalr\Service\CloudStack\DataType\TemplateResponseData;
use Scalr\Service\CloudStack\DataType\ExtractTemplateResponseData;
use Scalr\Service\CloudStack\DataType\TemplatePermissionsList;
use Scalr\Service\CloudStack\DataType\TemplatePermissionsData;
use Scalr\Service\CloudStack\DataType\DetailsData;
use DateTime, DateTimeZone;

/**
 * TemplateTrait
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
trait TemplateTrait
{
    /**
     * Loads TemplateResponseList from json object
     *
     * @param   object $templatesList
     * @return  TemplateResponseList Returns TemplateResponseList
     */
    protected function _loadTemplateResponseList($templatesList)
    {
        $result = new TemplateResponseList();

        if (!empty($templatesList)) {
            foreach ($templatesList as $template) {
                $item = $this->_loadTemplateResponseData($template);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads TemplateResponseData from json object
     *
     * @param   object $resultObject
     * @return  TemplateResponseData Returns TemplateResponseData
     */
    protected function _loadTemplateResponseData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new TemplateResponseData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if ('created' == $property) {
                        $item->created = new DateTime((string)$resultObject->created, new DateTimeZone('UTC'));
                    }
                    else if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected stdObject class received in property ' . $property, E_USER_WARNING);
                    }
                    else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
            if (property_exists($resultObject, 'tags')) {
                $item->setTags($this->_loadTagsList($resultObject->tags));
            }
            if (property_exists($resultObject, 'details')) {
                $item->setDetails($this->_loadDetailsData($resultObject->details));
            }
        }

        return $item;
    }

    /**
     * Loads DetailsData from json object
     *
     * @param   object $resultObject
     * @return  DetailsData Returns DetailsData
     */
    public function _loadDetailsData($resultObject)
    {
        $item = null;

        if (!empty($resultObject) && is_object($resultObject)) {
            $item = new DetailsData();
            $item->rootDiskController = property_exists($resultObject, 'rootDiskController') ? $resultObject->rootDiskController : null;
        }

        return $item;
    }

    /**
     * Loads ExtractTemplateResponseData from json object
     *
     * @param   object $resultObject
     * @return  ExtractTemplateResponseData Returns ExtractTemplateResponseData
     */
    protected function _loadExtractTemplateData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new ExtractTemplateResponseData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if ('created' == $property) {
                        $item->created = new DateTime((string)$resultObject->created, new DateTimeZone('UTC'));
                    }
                    else if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected stdObject class received in property ' . $property, E_USER_WARNING);
                    }
                    else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
        }

        return $item;
    }

    /**
     * Loads TemplatePermissionsList from json object
     *
     * @param   object $permissionsList
     * @return  TemplatePermissionsList Returns TemplatePermissionsList
     */
    protected function _loadTemplatePermissionsList($permissionsList)
    {
        $result = new TemplatePermissionsList();

        if (!empty($permissionsList)) {
            foreach ($permissionsList as $permission) {
                $item = $this->_loadTemplatePermissionsData($permission);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads TemplatePermissionsData from json object
     *
     * @param   object $resultObject
     * @return  TemplatePermissionsData Returns TemplatePermissionsData
     */
    protected function _loadTemplatePermissionsData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new TemplatePermissionsData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                        $item->{$property} = json_encode($resultObject->{$property});
                    } else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
        }

        return $item;
    }

}