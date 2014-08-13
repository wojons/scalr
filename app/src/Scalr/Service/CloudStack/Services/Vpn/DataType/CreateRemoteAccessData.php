<?php
namespace Scalr\Service\CloudStack\Services\Vpn\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * CreateRemoteAccessData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class CreateRemoteAccessData extends AbstractDataType
{

    /**
     * Required
     * Public ip address id of the vpn server
     *
     * @var string
     */
    public $publicipid;

    /**
     * An optional account for the VPN.
     * Must be used with domainId.
     *
     * @var string
     */
    public $account;

    /**
     * An optional domainId for the VPN.
     * If the account parameter is used, domainId must also be used.
     *
     * @var string
     */
    public $domainid;

    /**
     * The range of ip addresses to allocate to vpn clients.
     * The first ip in the range will be taken by the vpn server
     *
     * @var string
     */
    public $iprange;

    /**
     * if true, firewall rule for source/end pubic port is automatically created;
     * if false - firewall rule has to be created explicitely.
     * Has value true by default
     *
     * @var string
     */
    public $openfirewall;

    /**
     * Constructor
     *
     * @param   string  $publicipid     Public ip address id of the vpn server
     */
    public function __construct($publicipid)
    {
        $this->publicipid = $publicipid;
    }

}
