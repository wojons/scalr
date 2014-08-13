<?php
namespace Scalr\Service\OpenStack\Services\Network\Type;

use Scalr\Service\OpenStack\Type\AbstractInitType;

/**
 * CreateLbPool
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (15.01.2014)
 */
class CreateLbPool extends AbstractInitType
{

    /**
     * Owner of the pool. Only admin users can specify a tenant identifier other than its own
     *
     * @var string
     */
    public $tenant_id;

    /**
     * The protocol of the pool
     *
     * @var string
     */
    public $protocol;

    /**
     * The algorithm used to distribute load between the members of the pool.
     *
     * @var string
     */
    public $lb_method;

    /**
     * Human readable name for the pool. Does not have to be unique
     *
     * @var string
     */
    public $name;

    /**
     * The network that pool members belong to.
     *
     * @var string
     */
    public $subnet_id;

    /**
     * The Health Monitors list
     *
     * @var array
     */
    public $health_monitors;

    /**
     * Administrative state of the pool
     *
     * @var boolean
     */
    public $admin_state_up;

    /**
     * Human readable description for the pool.
     *
     * @var string
     */
    public $description;

    /**
     * Constructor
     * @param   string     $protocol  The protocol of the pool
     * @param   string     $lbMethod  The algorithm used to distribute load between the members of the pool.
     * @param   string     $subnetId  The network that pool members belong to.
     * @param   string     $tenantId  optional The owner of the pool (Derived from the authentication token)
     */
    public function __construct($protocol = null, $lbMethod = null, $subnetId = null, $tenantId = null)
    {
        $this->protocol = $protocol;
        $this->lb_method = $lbMethod;
        $this->subnet_id = $subnetId;
        $this->tenant_id = $tenantId;
    }

    /**
     * Initializes a new CreateLbPool object
     *
     * @param   string     $protocol  The protocol of the pool
     * @param   string     $lbMethod  The algorithm used to distribute load between the members of the pool.
     * @param   string     $subnetId  The network that pool members belong to.
     * @param   string     $tenantId  optional The owner of the pool (Derived from the authentication token)
     * @return  CreateLbPool
     */
    public static function init()
    {
        return call_user_func_array('parent::init', func_get_args());
    }
}