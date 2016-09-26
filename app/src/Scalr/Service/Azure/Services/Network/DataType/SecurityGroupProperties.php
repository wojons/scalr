<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * SecurityGroupProperties
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.9
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\SecurityRuleList  $securityRules
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\SecurityRuleList  $defaultSecurityRules
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\InterfaceList  $networkInterfaces
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\SubnetList  $subnets
 *
 */
class SecurityGroupProperties extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['securityRules', 'defaultSecurityRules', 'networkInterfaces', 'subnets'];

    /**
     * Provisioning state of the Network Security Group.
     *
     * @var string
     */
    public $provisioningState;

    /**
     * Sets Security Rules
     *
     * @param   array|SecurityRuleList $securityRules
     * @return  SecurityGroupProperties
     */
    public function setSecurityRules($securityRules = null)
    {
        return $this->__call(__FUNCTION__, [$this->getRuleList($securityRules)]);
    }

    /**
     * Sets Default Security Rules
     *
     * @param   array|SecurityRuleList $securityRules
     * @return  SecurityGroupProperties
     */
    public function setDefaultSecurityRules($securityRules = null)
    {
        return $this->__call(__FUNCTION__, [$this->getRuleList($securityRules)]);
    }

    /**
     * Sets InterfaceList
     *
     * @param   array|InterfaceList  $networkInterfaces
     * @return  SecurityGroupProperties
     */
    public function setNetworkInterfaces($networkInterfaces = null)
    {
        if (!($networkInterfaces instanceof InterfaceList)) {
            $networkInterfaceList = new InterfaceList();

            foreach ($networkInterfaces as $networkInterface) {
                if (!($networkInterface instanceof InterfaceData)) {
                    $networkInterfaceData = InterfaceData::initArray($networkInterface);
                } else {
                    $networkInterfaceData = $networkInterface;
                }

                $networkInterfaceList->append($networkInterfaceData);
            }
        } else {
            $networkInterfaceList = $networkInterfaces;
        }

        return $this->__call(__FUNCTION__, [$networkInterfaceList]);
    }

    /**
     * Sets subnets
     *
     * @param   array|SubnetList $subnets
     * @return  SecurityGroupProperties
     */
    public function setSubnets($subnets = null)
    {
        if (!($subnets instanceof SubnetList)) {
            $subnetList = new SubnetList();

            foreach ($subnets as $subnet) {
                if (!($subnet instanceof SubnetData)) {
                    $subnetData = SubnetData::initArray($subnet);
                } else {
                    $subnetData = $subnet;
                }

                $subnetList->append($subnetData);
            }
        } else {
            $subnetList = $subnets;
        }

        return $this->__call(__FUNCTION__, [$subnetList]);
    }

    /**
     * Gets Security Rules list
     *
     * @param   array|SecurityRuleList $securityRules
     * @return  SecurityRuleList
     */
    private function getRuleList($securityRules)
    {
        if (!($securityRules instanceof SecurityRuleList)) {
            $securityRuleList = new SecurityRuleList();

            foreach ($securityRules as $securityRule) {
                if (!($securityRule instanceof SecurityRuleData)) {
                    $securityRuleData = SecurityRuleData::initArray($securityRule);
                } else {
                    $securityRuleData = $securityRule;
                }

                $securityRuleList->append($securityRuleData);
            }
        } else {
            $securityRuleList = $securityRules;
        }

        return $securityRuleList;
    }

}