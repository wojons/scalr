<?php
namespace Scalr\Service\CloudStack\Services\Vpn\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * AddVpnUserData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class AddVpnUserData extends AbstractDataType
{

    /**
     * Required
     * Password for the username
     *
     * @var string
     */
    public $password;

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
     * @param   string  $password     Password for the username
     * @param   string  $username     Username for the vpn user
     */
    public function __construct($password, $username)
    {
        $this->password = $password;
        $this->username = $username;
    }

}
