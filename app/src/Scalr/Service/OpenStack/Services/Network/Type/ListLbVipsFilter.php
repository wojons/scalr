<?php
namespace Scalr\Service\OpenStack\Services\Network\Type;

use Scalr\Service\OpenStack\Type\Marker;
use Scalr\Service\OpenStack\Type\BooleanType;

/**
 * ListLbVipsFilter
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (14.01.2014)
 *
 * @method   array getId() getId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter setId()
 *           setId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter addId()
 *           addId($value)
 *
 * @method   array getName() getName()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter setName()
 *           setName($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter addName()
 *           addName($value)
 *
 * @method   array getAdminStateUp() getAdminStateUp()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter addAdminStateUp()
 *           addAdminStateUp($value)
 *
 * @method   array getProtocol() getProtocol()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter setProtocol()
 *           setProtocol($protocol)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter addProtocol()
 *           addProtocol($protocol)
 *
 * @method   array getStatus() getStatus()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter setStatus()
 *           setStatus($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter addStatus()
 *           addStatus($value)
 *
 * @method   array getSubnetId() getSubnetId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter setSubnetId()
 *           setSubnetId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter addSubnetId()
 *           addSubnetId($value)
 *
 * @method   array getTenantId() getTenantId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter setTenantId()
 *           setTenantId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter addTenantId()
 *           addTenantId($value)
 *
 * @method   array getPoolId() getPoolId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter setPoolId()
 *           setPoolId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter addPoolId()
 *           addPoolId($value)
 *
 * @method   array getAddress() getAddress()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter setAddress()
 *           setAddress($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter addAddress()
 *           addAddress($value)
 *
 * @method   array getProtocolPort() getProtocolPort()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter setProtocolPort()
 *           setProtocolPort($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter addProtocolPort()
 *           addProtocolPort($value)
 *
 * @method   array getPortId() getPortId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter setPortId()
 *           setPortId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter addPortId()
 *           addPortId($value)
 */
class ListLbVipsFilter extends Marker
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

    private $poolId;

    private $address;

    private $protocolPort;

    private $portId;

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