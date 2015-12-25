<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * SecurityRuleProperties
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.9
 *
 */
class SecurityRuleProperties extends AbstractDataType
{
    const DIRECTION_INBOUND = 'Inbound';

    /**
     * Provisioning state of the Security Rule
     *
     * @var string
     */
    public $provisioningState;

    /**
     * A description for this rule.
     *
     * @var string
     */
    public $description;

    /**
     * Network protocol this rule applies to.
     *
     * @var string
     */
    public $protocol;

    /**
     * Source Port or Range.
     *
     * @var string
     */
    public $sourcePortRange;

    /**
     * Destination Port or Range.
     *
     * @var string
     */
    public $destinationPortRange;

    /**
     * CIDR or source IP range or * to match any IP.
     *
     * @var string
     */
    public $sourceAddressPrefix;

    /**
     * CIDR or destination IP range or * to match any IP.
     *
     * @var string
     */
    public $destinationAddressPrefix;

    /**
     * Specifies whether network traffic is allowed or denied.
     *
     * @var string
     */
    public $access;

    /**
     * Specifies the priority of the rule.
     *
     * @var int
     */
    public $priority;

    /**
     * The direction specifies if rule will be evaluated on incoming or outgoing traffic.
     *
     * @var string
     */
    public $direction;

}