<?php
namespace Scalr\Service\OpenStack\Services\Network\Type;

use Scalr\Service\OpenStack\Type\Marker;

/**
 * ListSecurityGroupRulesFilter
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    11.08.2014
 *
 *
 * @method   array getProtocol() getProtocol()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter setProtocol()
 *           setProtocol($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter addProtocol()
 *           addProtocol($value)
 *
 *
 * @method   array getId() getId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter setId()
 *           setId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter addId()
 *           addId($value)
 *
 *
 * @method   array getSecurityGroupId() getSecurityGroupId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter setSecurityGroupId()
 *           setSecurityGroupId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter addSecurityGroupId()
 *           addSecurityGroupId($value)
 *
 *
 * @method   array getTenantId() getTenantId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter setTenantId()
 *           setTenantId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter addTenantId()
 *           addTenantId($value)
 *
 *
 * @method   array getDirection() getDirection()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter setDirection()
 *           setDirection($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter addDirection()
 *           addDirection($value)
 *
 *
 * @method   array getEthertype() getEthertype()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter setEthertype()
 *           setEthertype($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter addEthertype()
 *           addEthertype($value)
 *
 *
 * @method   array getPortRangeMax() getPortRangeMax()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter setPortRangeMax()
 *           setPortRangeMax($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter addPortRangeMax()
 *           addPortRangeMax($value)
 *
 *
 * @method   array getPortRangeMin() getPortRangeMin()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter setPortRangeMin()
 *           setPortRangeMin($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter addPortRangeMin()
 *           addPortRangeMin($value)
 *
 *
 * @method   array getRemoteGroupId() getRemoteGroupId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter setRemoteGroupId()
 *           setRemoteGroupId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter addRemoteGroupId()
 *           addRemoteGroupId($value)
 *
 *
 * @method   array getRemoteIpPrefix() getRemoteIpPrefix()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter setRemoteIpPrefix()
 *           setRemoteIpPrefix($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter addRemoteIpPrefix()
 *           addRemoteIpPrefix($value)
 */
class ListSecurityGroupRulesFilter extends Marker
{
    private $id;

    private $securityGroupId;

    private $tenantId;

    private $direction;

    private $ethertype;

    private $portRangeMax;

    private $portRangeMin;

    private $protocol;

    private $remoteGroupId;

    private $remoteIpPrefix;

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