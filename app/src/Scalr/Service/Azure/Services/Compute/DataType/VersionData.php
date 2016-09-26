<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * VersionData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class VersionData extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['plan'];
    
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $location;
    
    /**
     * Sets plan
     *
     * @param   array|PlanProperties $plan Required for marketplace images
     * @return  VersionData
     */
    public function setPlan($plan = null)
    {
        if (!($plan instanceof PlanProperties)) {
            $plan = PlanProperties::initArray($plan);
        }
    
        return $this->__call(__FUNCTION__, [$plan]);
    }
}