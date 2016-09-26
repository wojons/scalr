<?php
namespace Scalr\Service\OpenStack\Services\Network\Type;

use Scalr\Service\OpenStack\Type\Marker;
use Scalr\Service\OpenStack\Type\BooleanType;

/**
 * ListLbMembersFilter
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (15.01.2014)
 *
 * @method   array getId() getId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbMembersFilter setId()
 *           setId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbMembersFilter addId()
 *           addId($value)
 *
 * @method   array getAdminStateUp() getAdminStateUp()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbMembersFilter addAdminStateUp()
 *           addAdminStateUp($value)
 *
 * @method   array getStatus() getStatus()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbMembersFilter setStatus()
 *           setStatus($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbMembersFilter addStatus()
 *           addStatus($value)
 *
 * @method   array getTenantId() getTenantId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbMembersFilter setTenantId()
 *           setTenantId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbMembersFilter addTenantId()
 *           addTenantId($value)
 *
 * @method   array getPoolId() getPoolId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbMembersFilter setPoolId()
 *           setPoolId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbMembersFilter addPoolId()
 *           addPoolId($value)
 *
 * @method   array getAddress() getAddress()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbMembersFilter setAddress()
 *           setAddress($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbMembersFilter addAddress()
 *           addAddress($value)
 *
 * @method   array getProtocolPort() getProtocolPort()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbMembersFilter setProtocolPort()
 *           setProtocolPort($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbMembersFilter addProtocolPort()
 *           addProtocolPort($value)
 *
 * @method   array getWeight() getWeight()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbMembersFilter setWeight()
 *           setWeight($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbMembersFilter addWeight()
 *           addWeight($value)
 */
class ListLbMembersFilter extends Marker
{

    /**
     * Filter by id
     *
     * @var array
     */
    private $id;

    private $status;

    /**
     * Filter by AdminStateUp
     *
     * @var BooleanType
     */
    private $adminStateUp;

    private $tenantId;

    private $poolId;

    private $address;

    private $protocolPort;

    private $weight;

    /**
     * Convenient constructor
     *
     * @param   string|array        $id           optional The one or more ID
     * @param   string              $marker       optional A marker.
     * @param   int                 $limit        optional Limit.
     */
    public function __construct($id = null, $marker = null, $limit = null)
    {
        parent::__construct($marker, $limit);
        $this->setId($id);
    }

    /**
     * Initializes new object
     *
     * @param   string|array        $id           optional The one or more ID
     * @param   string              $marker       optional A marker.
     * @param   int                 $limit        optional Limit.
     * @return  ListSubnetsFilter   Returns a new ListSubnetsFilter object
     */
    public static function init()
    {
        return call_user_func_array('parent::init', func_get_args());
    }

    /**
     * Sets the admin state flag
     *
     * @param   boolean $adminStateUp The admin state flag
     * @return  ListNetworksFilter
     */
    public function setAdminStateUp($adminStateUp = null)
    {
        $this->adminStateUp = $adminStateUp !== null ? BooleanType::init($adminStateUp) : null;
        return $this;
    }
}