<?php
namespace Scalr\Service\OpenStack\Services\Network\Type;

use Scalr\Service\OpenStack\Type\AbstractInitType;

/**
 * CreateLbVip
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (16.01.2014)
 */
class CreateLbVip extends AbstractInitType
{

    /**
     * Owner of the VIP. Only admin users can specify a tenant identifier other than its own
     *
     * @var string
     */
    public $tenant_id;

    /**
     * Human readable name for the vip.
     * Does not have to be unique
     *
     * @var string
     */
    public $name;

    /**
     * Human readable description for the vip.
     *
     * @var string
     */
    public $description;

    /**
     * The subnet on which to allocate the vip address.
     *
     * @var string
     */
    public $subnet_id;

    /**
     * The IP address of the vip.
     *
     * @var string
     */
    public $address;

    /**
     * The protocol of the vip address
     *
     * @var string
     */
    public $protocol;

    /**
     * The port on which to listen to client traffic that is associated with the vip address.
     *
     * @var int
     */
    public $protocol_port;

    /**
     * The pool that the vip associated with.
     *
     * @var string
     */
    public $pool_id;

    /**
     * Session persistence parameters of the vip
     *
     * Session persistence is a dictionary with the following attributes:
     * type - any of APP_COOKIE, HTTP_COOKIE or SOURCE_IP
     * cookie_name - any string, required if type is APP_COOKIE
     *
     * @var array
     */
    public $session_persistence;

    /**
     * The maximum number of connections allowed for the vip or "-1" if the limit is not set
     *
     * @var int
     */
    public $connection_limit;

    /**
     * Administrative state of the health monitor
     *
     * @var boolean
     */
    public $admin_state_up;

    /**
     * Constructor
     *
     * @param   string $protocol      The protocol
     * @param   int    $protocolPort  The port on which to listen to client traffic
     * @param   string $subnetId      optional The identifier of the subnet on which to allocate the vip address.
     * @param   string $poolId        optional The identifier of the pool that the vip associated with
     * @param   string $tenantId      optional The owner of the health monitor
     */
    public function __construct($protocol = null, $protocolPort = null, $subnetId = null, $poolId = null, $tenantId = null)
    {
        $this->tenant_id = $tenantId;
        $this->protocol = $protocol;
        $this->protocol_port = $protocolPort;
        $this->pool_id = $poolId;
        $this->subnet_id = $subnetId;
    }

    /**
     * Initializes a new CreateLbVip object
     *
     * @param   string $protocol      The protocol
     * @param   int    $protocolPort  The port on which to listen to client traffic
     * @param   string $subnetId      optional The identifier of the subnet on which to allocate the vip address.
     * @param   string $poolId        optional The identifier of the pool that the vip associated with
     * @param   string $tenantId      optional The owner of the health monitor
     * @return  CreateLbVip
     */
    public static function init()
    {
        return call_user_func_array('parent::init', func_get_args());
    }
}