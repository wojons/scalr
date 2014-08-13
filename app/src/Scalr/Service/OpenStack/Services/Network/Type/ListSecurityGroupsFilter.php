<?php
namespace Scalr\Service\OpenStack\Services\Network\Type;

use Scalr\Service\OpenStack\Type\Marker;

/**
 * ListSecurityGroupsFilter
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    11.08.2014
 *
 *
 * @method   array getName() getName()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter setName()
 *           setName($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter addName()
 *           addName($value)
 *
 * @method   array getId() getId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter setId()
 *           setId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter addId()
 *           addId($value)
 *
 * @method   array getDescription() getDescription()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter setDescription()
 *           setDescription($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter addDescription()
 *           addDescription($value)
 *
 * @method   array getTenantId() getTenantId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter setTenantId()
 *           setTenantId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter addTenantId()
 *           addTenantId($value)
 */
class ListSecurityGroupsFilter extends Marker
{
    private $id;

    private $name;

    private $description;

    private $tenantId;

    /**
     * Initializes new object
     *
     * @param   string              $marker       optional A marker.
     * @param   int                 $limit        optional Limit.
     * @return  ListSecurityGroupsFilter  Returns a new ListSecurityGroupsFilter object
     */
    public static function init()
    {
        return call_user_func_array('parent::init', func_get_args());
    }
}