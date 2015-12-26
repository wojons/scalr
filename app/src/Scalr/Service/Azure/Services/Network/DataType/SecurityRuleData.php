<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

/**
 * SecurityRuleData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.9
 *
 */
class SecurityRuleData extends CreateSecurityRule
{
    /**
     * The identifying URL of the Security Rule.
     *
     * @var string
     */
    public $id;

    /**
     * The name of the Security Rule.
     *
     * @var string
     */
    public $name;

    /**
     * System generated meta-data enabling concurrency control
     *
     * @var string
     */
    public $etag;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Sets properties
     *
     * @param   array|SecurityRuleProperties|CreateSecurityRule $properties
     * @return  SecurityRuleData
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof SecurityRuleProperties)) {
            if ($properties instanceof CreateSecurityRule) {
                $properties = $properties->properties->toArray();
            }
            $properties = SecurityRuleProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

}