<?php
namespace Scalr\Service\CloudStack\Services\Vpn\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * RemoveVpnUserData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class RemoveVpnUserData extends AbstractDataType
{

    /**
     * Required
     * Username for the vpn user
     *
     * @var string
     */
    public $username;

    /**
     * An optional account for the vpn user.
     * Must be used with domainId.
     *
     * @var string
     */
    public $account;

    /**
     * An optional domainId for the vpn user.
     * If the account parameter is used, domainId must also be used.
     *
     * @var string
     */
    public $domainid;

    /**
     * Add vpn user to the specific project
     *
     * @var string
     */
    public $projectid;

    /**
     * Constructor
     *
     * @param   string  $username     Username for the vpn user
     */
    public function __construct($username)
    {
        $this->username = $username;
    }

}
