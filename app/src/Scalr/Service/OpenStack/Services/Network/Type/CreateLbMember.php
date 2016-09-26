<?php
namespace Scalr\Service\OpenStack\Services\Network\Type;

use Scalr\Service\OpenStack\Type\AbstractInitType;

/**
 * CreateLbMember
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (16.01.2014)
 */
class CreateLbMember extends AbstractInitType
{

    /**
     * Owner of the pool. Only admin users can specify a tenant identifier other than its own
     *
     * @var string
     */
    public $tenant_id;

    /**
     * The pool that the member belongs to.
     *
     * @var string
     */
    public $pool_id;

    /**
     * The IP address of the member
     *
     * @var string
     */
    public $address;

    /**
     * The port on which the application is hosted.
     *
     * @var int
     */
    public $protocol_port;

    /**
     * The weight of a member determines the portion of requests or
     * connections it services compared to the other members of the pool.
     * A value of 0 means the member will not participate in load-balancing
     * but will still accept persistent connections.
     *
     * Available values: [0-256]
     *
     * @var int
     */
    public $weight;

    /**
     * Administrative state of the member
     *
     * @var boolean
     */
    public $admin_state_up;

    /**
     * Constructor
     * @param   string     $poolId       The pool that the member belongs to
     * @param   string     $address      The IP address of the member.
     * @param   string     $protocolPort The port on which the application is hosted.
     * @param   string     $tenantId     optional The owner of the member (Derived from the authentication token)
     */
    public function __construct($poolId = null, $address = null, $protocolPort = null, $tenantId = null)
    {
        $this->pool_id = $poolId;
        $this->address = $address;
        $this->protocolPort = $protocolPort;
        $this->tenant_id = $tenantId;
    }

    /**
     * Initializes a new CreateLbMember object
     *
     * @param   string     $poolId       The pool that the member belongs to
     * @param   string     $address      The IP address of the member.
     * @param   string     $protocolPort The port on which the application is hosted.
     * @param   string     $tenantId     optional The owner of the member (Derived from the authentication token)
     * @return  CreateLbMember
     */
    public static function init()
    {
        return call_user_func_array('parent::init', func_get_args());
    }
}