<?php
namespace Scalr\Service\OpenStack\Services\Network\Type;

use Scalr\Service\OpenStack\Type\AbstractInitType;

/**
 * CreateRouter
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    27.08.2013
 */
class CreateRouter extends AbstractInitType
{

    /**
     * The name of the router
     *
     * @var string
     */
    public $name;

    /**
     * @var boolean
     */
    public $admin_state_up;

    /**
     * @var object
     */
    public $external_gateway_info;

    /**
     * Constructor
     *
     * @param   string     $name                The name of the router
     * @param   boolean    $adminStateUp        optional Admin state up
     * @param   string     $externalNetworkUuid optional The identifier of the external network
     */
    public function __construct($name, $adminStateUp = true, $externalNetworkUuid = null)
    {
        $this->name = $name;
        $this->admin_state_up = $adminStateUp !== null ? (bool) $adminStateUp : null;
        if (!empty($externalNetworkUuid)) {
            $this->external_gateway_info = new \stdClass();
            $this->external_gateway_info->network_id = trim($externalNetworkUuid, '<>');
        }
    }

    /**
     * Initializes a new instance
     *
     * @param   string     $name                The name of the router
     * @param   boolean    $adminStateUp        optional Admin state up
     * @param   string     $externalNetworkUuid optional The identifier of the external network
     * @return  CreateRouter
     */
    public static function init()
    {
        return call_user_func_array('parent::init', func_get_args());
    }
}