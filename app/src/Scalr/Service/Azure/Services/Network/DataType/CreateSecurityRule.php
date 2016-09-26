<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * CreateSecurityRule
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.9
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\SecurityRuleProperties  $properties
 *
 */
class CreateSecurityRule extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['properties'];

    /**
     * Constructor
     *
     * @param string $protocol                      Network protocol this rule applies to.
     * @param string $sourcePortRange               Source Port or Range.
     * @param string $destinationPortRange          Destination Port or Range.
     * @param string $sourceAddressPrefix           CIDR or source IP range or * to match any IP.
     * @param string $destinationAddressPrefix      CIDR or destination IP range or * to match any IP.
     * @param string $access                        Specifies whether network traffic is allowed or denied.
     * @param int    $priority                      Specifies the priority of the rule.
     * @param string $direction                     The direction specifies if rule will be evaluated on incoming or outgoing traffic.
     * @param string $description                   optional A description for this rule
     */
    public function __construct($protocol, $sourcePortRange, $destinationPortRange,
                                $sourceAddressPrefix, $destinationAddressPrefix,
                                $access, $priority, $direction, $description = null)
    {
        $properties = new SecurityRuleProperties();
        $properties->protocol                    = $protocol;
        $properties->sourcePortRange             = $sourcePortRange;
        $properties->destinationPortRange        = $destinationPortRange;
        $properties->sourceAddressPrefix         = $sourceAddressPrefix;
        $properties->destinationAddressPrefix    = $destinationAddressPrefix;
        $properties->access                      = $access;
        $properties->priority                    = $priority;
        $properties->direction                   = $direction;

        if (!empty($description)) {
            $properties->description = $description;
        }

        $this->setProperties($properties);
    }

    /**
     * Sets properties
     *
     * @param   array|SecurityRuleProperties $properties
     * @return  CreateSecurityRule
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof SecurityRuleProperties)) {
            $properties = SecurityRuleProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

}