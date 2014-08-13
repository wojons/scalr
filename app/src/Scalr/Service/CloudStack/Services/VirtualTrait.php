<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\DataType\AffinityGroupData;
use Scalr\Service\CloudStack\DataType\AffinityGroupList;
use Scalr\Service\CloudStack\DataType\EgressruleData;
use Scalr\Service\CloudStack\DataType\EgressruleList;
use Scalr\Service\CloudStack\DataType\IngressruleData;
use Scalr\Service\CloudStack\DataType\IngressruleList;
use Scalr\Service\CloudStack\DataType\SecurityGroupData;
use Scalr\Service\CloudStack\DataType\SecurityGroupList;
use Scalr\Service\CloudStack\DataType\NicData;
use Scalr\Service\CloudStack\DataType\NicList;
use Scalr\Service\CloudStack\DataType\VirtualMachineInstancesData;
use Scalr\Service\CloudStack\DataType\VirtualMachineInstancesList;
use Scalr\Service\CloudStack\DataType\VirtualDetailsData;
use DateTime, DateTimeZone;

/**
 * VirtualTrait
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
trait VirtualTrait
{
    /**
     * Loads VirtualMachineInstancesList from json object
     *
     * @param   object $instancesList
     * @return  VirtualMachineInstancesList Returns VirtualMachineInstancesList
     */
    protected function _loadVirtualMachineInstancesList($instancesList)
    {
        $result = new VirtualMachineInstancesList();

        if (!empty($instancesList)) {
            foreach ($instancesList as $instance) {
                $item = $this->_loadVirtualMachineInstanceData($instance);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads VirtualMachineInstancesData from json object
     *
     * @param   object $resultObject
     * @return  VirtualMachineInstancesData Returns VirtualMachineInstancesData
     */
    protected function _loadVirtualMachineInstanceData($resultObject)
    {
        $item = new VirtualMachineInstancesData();
        $properties = get_object_vars($item);

        foreach($properties as $property => $value) {
            if (property_exists($resultObject, "$property")) {
                if ('created' == $property) {
                    $item->created = new DateTime((string)$resultObject->created, new DateTimeZone('UTC'));
                } else if (is_object($resultObject->{$property})) {
                    // Fix me. Temporary fix.
                    trigger_error('Cloudstack error. Unexpected sdt object class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                    $item->{$property} = json_encode($resultObject->{$property});
                } else {
                    $item->{$property} = (string) $resultObject->{$property};
                }
            }
        }
        if (property_exists($resultObject, 'affinitygroup')) {
            $item->setAffinitygroup($this->_loadAffinityGroupList($resultObject->affinitygroup));
        }
        if (property_exists($resultObject, 'nic')) {
            $item->setNic($this->_loadNicList($resultObject->nic));
        }
        if (property_exists($resultObject, 'securitygroup')) {
            $item->setSecuritygroup($this->_loadSecurityGroupList($resultObject->securitygroup));
        }
        if (property_exists($resultObject, 'tags')) {
            $item->setTags($this->_loadTagsList($resultObject->tags));
        }
        if (property_exists($resultObject, 'details')) {
            $item->setDetails($this->_loadVirtualDetailsData($resultObject->details));
        }

        return $item;
    }

    /**
     * Loads AffinityGroupList from json object
     *
     * @param   object $affinityGroupList
     * @return  AffinityGroupList Returns AffinityGroupList
     */
    protected function _loadAffinityGroupList($affinityGroupList)
    {
        $result = new AffinityGroupList();

        if (!empty($affinityGroupList)) {
            foreach ($affinityGroupList as $affinityGroup) {
                $item = $this->_loadAffinityGroupData($affinityGroup);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads AffinityGroupData from json object
     *
     * @param   object $resultObject
     * @return  AffinityGroupData Returns AffinityGroupData
     */
    protected function _loadAffinityGroupData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new AffinityGroupData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected sdt object class received in property ' . $property, E_USER_WARNING);
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
     * Loads NicList from json object
     *
     * @param   object $nicList
     * @return  NicList Returns NicList
     */
    protected function _loadNicList($nicList)
    {
        $result = new NicList();

        if (!empty($nicList)) {
            foreach ($nicList as $nic) {
                $item = $this->_loadNicData($nic);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads NicData from json object
     *
     * @param   object $resultObject
     * @return  NicData Returns NicData
     */
    protected function _loadNicData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new NicData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected sdt object class received in property ' . $property, E_USER_WARNING);
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
     * Loads SecurityGroupList from json object
     *
     * @param   object $securityGroupList
     * @return  SecurityGroupList Returns SecurityGroupList
     */
    protected function _loadSecurityGroupList($securityGroupList)
    {
        $result = new SecurityGroupList();

        if (!empty($securityGroupList)) {
            foreach ($securityGroupList as $securityGroup) {
                $item = $this->_loadSecurityGroupData($securityGroup);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads SecurityGroupData from json object
     *
     * @param   object $resultObject
     * @return  SecurityGroupData Returns SecurityGroupData
     */
    protected function _loadSecurityGroupData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new SecurityGroupData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected sdt object class received in property ' . $property, E_USER_WARNING);
                    }
                    else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
            if (property_exists($resultObject, 'tags')) {
                $item->setTags($this->_loadTagsList($resultObject->tags));
            }
            if (property_exists($resultObject, 'egressrule')) {
                $item->setEgressrule($this->_loadEgressruleList($resultObject->egressrule));
            }
            if (property_exists($resultObject, 'ingressrule')) {
                $item->setIngressrule($this->_loadIngressruleList($resultObject->ingressrule));
            }
        }

        return $item;
    }

    /**
     * Loads EgressruleList from json object
     *
     * @param   object $egressruleList
     * @return  EgressruleList Returns EgressruleList
     */
    protected function _loadEgressruleList($egressruleList)
    {
        $result = new EgressruleList();

        if (!empty($egressruleList)) {
            foreach ($egressruleList as $egressrule) {
                $item = $this->_loadEgressruleData($egressrule);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads EgressruleData from json object
     *
     * @param   object $resultObject
     * @return  EgressruleData Returns EgressruleData
     */
    protected function _loadEgressruleData($resultObject)
    {
        $item = null;

        if (!empty($resultObject)) {
            $item = new EgressruleData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected sdt object class received in property ' . $property, E_USER_WARNING);
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
     * Loads IngressruleList from json object
     *
     * @param   object $ingressruleList
     * @return  IngressruleList Returns IngressruleList
     */
    protected function _loadIngressruleList($ingressruleList)
    {
        $result = new IngressruleList();

        if (!empty($ingressruleList)) {
            foreach ($ingressruleList as $ingressrule) {
                $item = $this->_loadIngressruleData($ingressrule);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads IngressruleData from json object
     *
     * @param   object $resultObject
     * @return  IngressruleData Returns IngressruleData
     */
    protected function _loadIngressruleData($resultObject)
    {
        $item = null;

        if (!empty($resultObject)) {
            $item = new IngressruleData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected sdt object class received in property ' . $property, E_USER_WARNING);
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
     * Loads VirtualDetailsData from json object
     *
     * @param   object $resultObject
     * @return  VirtualDetailsData Returns VirtualDetailsData
     */
    public function _loadVirtualDetailsData($resultObject)
    {
        $item = null;

        if (!empty($resultObject) && is_object($resultObject)) {
            $item = new VirtualDetailsData();
            $item->hypervisortoolsversion = property_exists($resultObject, 'hypervisortoolsversion') ? $resultObject->hypervisortoolsversion : null;
        }

        return $item;
    }

}