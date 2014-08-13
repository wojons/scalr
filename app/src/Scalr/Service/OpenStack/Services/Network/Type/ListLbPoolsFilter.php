<?php
namespace Scalr\Service\OpenStack\Services\Network\Type;

use Scalr\Service\OpenStack\Type\Marker;
use Scalr\Service\OpenStack\Type\BooleanType;

/**
 * ListLbPoolsFilter
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (15.01.2014)
 *
 * @method   array getId() getId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter setId()
 *           setId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter addId()
 *           addId($value)
 *
 * @method   array getName() getName()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter setName()
 *           setName($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter addName()
 *           addName($value)
 *
 * @method   array getAdminStateUp() getAdminStateUp()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter addAdminStateUp()
 *           addAdminStateUp($value)
 *
 * @method   array getProtocol() getProtocol()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter setProtocol()
 *           setProtocol($protocol)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter addProtocol()
 *           addProtocol($protocol)
 *
 * @method   array getStatus() getStatus()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter setStatus()
 *           setStatus($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter addStatus()
 *           addStatus($value)
 *
 * @method   array getSubnetId() getSubnetId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter setSubnetId()
 *           setSubnetId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter addSubnetId()
 *           addSubnetId($value)
 *
 * @method   array getTenantId() getTenantId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter setTenantId()
 *           setTenantId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter addTenantId()
 *           addTenantId($value)
 *
 * @method   array getLbMethod() getLbMethod()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter setLbMethod()
 *           setLbMethod($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter addLbMethod()
 *           addLbMethod($value)
 *
 * @method   array getDescription() getDescription()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter setDescription()
 *           setDescription($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter addDescription()
 *           addDescription($value)
 *
 * @method   array getVipId() getVipId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter setVipId()
 *           setVipId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter addVipId()
 *           addVipId($value)
 */
class ListLbPoolsFilter extends Marker
{

    /**
     * Filters the list of subnets by name
     *
     * @var array
     */
    private $name;

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

    private $protocol;

    private $subnetId;

    private $tenantId;

    private $lbMethod;

    private $description;

    private $vipId;

    /**
     * Convenient constructor
     *
     * @param   string|array        $name         optional The one or more name
     * @param   string|array        $id           optional The one or more ID
     * @param   string              $marker       optional A marker.
     * @param   int                 $limit        optional Limit.
     */
    public function __construct($name = null, $id = null, $marker = null, $limit = null)
    {
        parent::__construct($marker, $limit);
        $this->setName($name);
        $this->setId($id);
    }

    /**
     * Initializes new object
     *
     * @param   string|array        $name         optional The one or more name
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