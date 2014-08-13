<?php
namespace Scalr\Service\CloudStack\Services\Instance\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * DeployVirtualMachineData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class DeployVirtualMachineData extends AbstractDataType
{

    /**
     * Required
     * The ID of the service offering for the virtual machine
     *
     * @var string
     */
    public $serviceofferingid;

    /**
     * Required
     * The ID of the template for the virtual machine
     *
     * @var string
     */
    public $templateid;

    /**
     * Required
     * Availability zone for the virtual machine
     *
     * @var string
     */
    public $zoneid;

    /**
     * An optional account for the virtual machine. Must be used with domainId.
     *
     * @var string
     */
    public $account;

    /**
     * Comma separated list of affinity groups id that are going to be applied to the virtual machine.
     * Mutually exclusive with affinitygroupnames parameter
     *
     * @var string
     */
    public $affinitygroupids;

    /**
     * Comma separated list of affinity groups names that are going to be applied to the virtual machine.
     * Mutually exclusive with affinitygroupids parameter
     *
     * @var string
     */
    public $affinitygroupnames;

    /**
     * Used to specify the custom parameters.
     *
     * @var string
     */
    public $details;

    /**
     * the ID of the disk offering for the virtual machine.
     * If the template is of ISO format, the diskOfferingId is for the root disk volume.
     * Otherwise this parameter is used to indicate the offering for the data disk volume.
     * If the templateId parameter passed is from a Template object, the diskOfferingId refers to a DATA Disk Volume created.
     * If the templateId parameter passed is from an ISO object, the diskOfferingId refers to a ROOT Disk Volume created.
     *
     * @var string
     */
    public $diskofferingid;

    /**
     * An optional user generated name for the virtual machine
     *
     * @var string
     */
    public $displayname;

    /**
     * An optional field, whether to the display the vm to the end user or not.
     *
     * @var string
     */
    public $displayvm;

    /**
     * An optional group for the virtual machine
     *
     * @var string
     */
    public $group;

    /**
     * An optional domainId for the virtual machine.
     * If the account parameter is used, domainId must also be used.
     *
     * @var string
     */
    public $domainid;

    /**
     * Destination Host ID to deploy the VM to - parameter available for root admin only
     *
     * @var string
     */
    public $hostid;

    /**
     * The hypervisor on which to deploy the virtual machine
     *
     * @var string
     */
    public $hypervisor;

    /**
     * The ipv6 address for default vm's network
     *
     * @var string
     */
    public $ip6address;

    /**
     * The ip address for default vm's network
     *
     * @var string
     */
    public $ipaddress;

    /**
     * ip to network mapping.
     * Can't be specified with networkIds parameter.
     * Example: iptonetworklist[0].ip=10.10.10.11&iptonetworklist[0].ipv6=fc00:1234:5678::abcd&iptonetworklist[0].networkid=uuid - requests to use ip 10.10.10.11 in network id=uuid
     *
     * @var string
     */
    public $iptonetworklist;

    /**
     * An optional keyboard device type for the virtual machine. valid value can be one of de,de-ch,es,fi,fr,fr-be,fr-ch,is,it,jp,nl-be,no,pt,uk,us
     *
     * @var string
     */
    public $keyboard;

    /**
     * Name of the ssh key pair used to login to the virtual machine
     *
     * @var string
     */
    public $keypair;

    /**
     * Host name for the virtual machine
     *
     * @var string
     */
    public $name;

    /**
     * List of network ids used by virtual machine.
     * Can't be specified with ipToNetworkList parameter
     *
     * @var string
     */
    public $networkids;

    /**
     * Deploy vm for the project
     *
     * @var string
     */
    public $projectid;

    /**
     * Comma separated list of security groups id that going to be applied to the virtual machine.
     * Should be passed only when vm is created from a zone with Basic Network support.
     * Mutually exclusive with securitygroupnames parameter
     *
     * @var string
     */
    public $securitygroupids;

    /**
     * Comma separated list of security groups names that going to be applied to the virtual machine.
     * Should be passed only when vm is created from a zone with Basic Network support.
     * Mutually exclusive with securitygroupids parameter
     *
     * @var string
     */
    public $securitygroupnames;

    /**
     * The arbitrary size for the DATADISK volume. Mutually exclusive with diskOfferingId
     *
     * @var string
     */
    public $size;

    /**
     * True if network offering supports specifying ip ranges; defaulted to true if not specified
     *
     * @var string
     */
    public $startvm;

    /**
     * An optional binary data that can be sent to the virtual machine upon a successful deployment.
     * This binary data must be base64 encoded before adding it to the request.
     * Using HTTP GET (via querystring), you can send up to 2KB of data after base64 encoding.
     * Using HTTP POST(via POST body), you can send up to 32K of data after base64 encoding.
     *
     * @var string
     */
    public $userdata;

    /**
     * Constructor
     *
     * @param   string  $serviceofferingid        The ID of the service offering for the virtual machine
     * @param   string  $templateid               The ID of the template for the virtual machine
     * @param   string  $zoneId                   Availability zone for the virtual machine
     */
    public function __construct($serviceofferingid, $templateid, $zoneId)
    {
        $this->serviceofferingid = $serviceofferingid;
        $this->templateid = $templateid;
        $this->zoneid = $zoneId;
    }

}
