<?php
namespace Scalr\Service\CloudStack\Services\Firewall\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * EnableStaticNatData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class EnableStaticNatData extends AbstractDataType
{

    /**
     * Required
     * The public IP address id for which static nat feature is being enabled
     *
     * @var string
     */
    public $ipaddressid;

    /**
     * Required
     * The ID of the virtual machine for enabling static nat feature
     *
     * @var string
     */
    public $virtualmachineid;

    /**
     * The network of the vm the static nat will be enabled for.
     * Required when public Ip address is not associated with any Guest network yet (VPC case)
     *
     * @var string
     */
    public $networkid;

    /**
     * VM guest nic Secondary ip address for the port forwarding rule
     *
     * @var string
     */
    public $vmguestip;

    /**
     * Constructor
     *
     * @param   string  $ipaddressid         The public IP address id for which static nat feature is being enabled
     * @param   string  $virtualmachineid    The ID of the virtual machine for enabling static nat feature
     */
    public function __construct($ipaddressid, $virtualmachineid)
    {
        $this->ipaddressid = $ipaddressid;
        $this->virtualmachineid = $virtualmachineid;
    }

}
