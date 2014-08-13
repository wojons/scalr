<?php
namespace Scalr\Service\CloudStack\DataType;

use \DateTime;

/**
 * VirtualMachineInstancesData
 *
 * @property  \Scalr\Service\CloudStack\DataType\ResponseTagsList       $tags
 * The list of resource tags associated with vm
 *
 * @property  \Scalr\Service\CloudStack\DataType\AffinityGroupList      $affinitygroup
 * The list of affinity groups associated with the virtual machine
 *
 * @property  \Scalr\Service\CloudStack\DataType\NicList                $nic
 * The list of nics associated with vm
 *
 * @property  \Scalr\Service\CloudStack\DataType\SecurityGroupList      $securitygroup
 * The list of security groups associated with the virtual machine
 *
 * @property  \Scalr\Service\CloudStack\DataType\VirtualDetailsData      $details
 * Vm details in key/value pairs.
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class VirtualMachineInstancesData extends JobStatusData
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('tags', 'affinitygroup', 'nic', 'securitygroup', 'details');

    /**
     * The ID of the virtual machine
     *
     * @var string
     */
    public $id;

    /**
     * The account associated with the virtual machine
     *
     * @var string
     */
    public $account;

    /**
     * The number of cpu this virtual machine is running with
     *
     * @var string
     */
    public $cpunumber;

    /**
     * The speed of each cpu
     *
     * @var string
     */
    public $cpuspeed;

    /**
     * The amount of the vm's CPU currently used
     *
     * @var string
     */
    public $cpuused;

    /**
     * The date when this virtual machine was created
     *
     * @var DateTime
     */
    public $created;

    /**
     * The read (io) of disk on the vm
     *
     * @var string
     */
    public $diskioread;

    /**
     * The write (io) of disk on the vm
     *
     * @var string
     */
    public $diskiowrite;

    /**
     * The read (bytes) of disk on the vm
     *
     * @var string
     */
    public $diskkbsread;

    /**
     * The write (bytes) of disk on the vm
     *
     * @var string
     */
    public $diskkbswrite;

    /**
     * User generated name.
     * The name of the virtual machine is returned if no displayname exists.
     *
     * @var string
     */
    public $displayname;

    /**
     * An optional field whether to the display the vm to the end user or not.
     *
     * @var string
     */
    public $displayvm;

    /**
     * The name of the domain in which the virtual machine exists
     *
     * @var string
     */
    public $domain;

    /**
     * The ID of the domain in which the virtual machine exists
     *
     * @var string
     */
    public $domainid;

    /**
     * The virtual network for the service offering
     *
     * @var string
     */
    public $forvirtualnetwork;

    /**
     * The group name of the virtual machine
     *
     * @var string
     */
    public $group;

    /**
     * The group ID of the virtual machine
     *
     * @var string
     */
    public $groupid;

    /**
     * Os type ID of the virtual machine
     *
     * @var string
     */
    public $guestosid;

    /**
     * true if high-availability is enabled,
     * false otherwise
     *
     * @var string
     */
    public $haenable;

    /**
     * The ID of the host for the virtual machine
     *
     * @var string
     */
    public $hostid;

    /**
     * The name of the host for the virtual machine
     *
     * @var string
     */
    public $hostname;

    /**
     * The hypervisor on which the template runs
     *
     * @var string
     */
    public $hypervisor;

    /**
     * Instance name of the user vm;
     * this parameter is returned to the ROOT admin only
     *
     * @var string
     */
    public $instancename;

    /**
     * true if vm contains XS/VMWare tools inorder to support dynamic scaling of VM cpu/memory.
     *
     * @var string
     */
    public $isdynamicallyscalable;

    /**
     * An alternate display text of the ISO attached to the virtual machine
     *
     * @var string
     */
    public $isodisplaytext;

    /**
     * The ID of the ISO attached to the virtual machine
     *
     * @var string
     */
    public $isoid;

    /**
     * The name of the ISO attached to the virtual machine
     *
     * @var string
     */
    public $isoname;

    /**
     * Ssh key-pair
     *
     * @var string
     */
    public $keypair;

    /**
     * The memory allocated for the virtual machine
     *
     * @var string
     */
    public $memory;

    /**
     * The name of the virtual machine
     *
     * @var string
     */
    public $name;

    /**
     * The incoming network traffic on the vm
     *
     * @var string
     */
    public $networkkbsread;

    /**
     * The outgoing network traffic on the host
     *
     * @var string
     */
    public $networkkbswrite;

    /**
     * The password (if exists) of the virtual machine
     *
     * @var string
     */
    public $password;

    /**
     * true if the password rest feature is enabled,
     * false otherwise
     *
     * @var string
     */
    public $passwordenabled;

    /**
     * The project name of the vm
     *
     * @var string
     */
    public $project;

    /**
     * The project id of the vm
     *
     * @var string
     */
    public $projectid;

    /**
     * Public IP address associated with vm via Static nat rule
     *
     * @var string
     */
    public $publicip;

    /**
     * Public IP address id associated with vm via Static nat rule
     *
     * @var string
     */
    public $publicipid;

    /**
     * Device ID of the root volume
     *
     * @var string
     */
    public $rootdeviceid;

    /**
     * Device type of the root volume
     *
     * @var string
     */
    public $rootdevicetype;

    /**
     * The ID of the service offering of the virtual machine
     *
     * @var string
     */
    public $serviceofferingid;

    /**
     * The name of the service offering of the virtual machine
     *
     * @var string
     */
    public $serviceofferingname;

    /**
     * State of the Service from LB rule
     *
     * @var string
     */
    public $servicestate;

    /**
     * The state of the virtual machine
     *
     * @var string
     */
    public $state;

    /**
     * An alternate display text of the template for the virtual machine
     *
     * @var string
     */
    public $templatedisplaytext;

    /**
     * The ID of the template for the virtual machine.
     * A -1 is returned if the virtual machine was created from an ISO file.
     *
     * @var string
     */
    public $templateid;

    /**
     * The name of the template for the virtual machine
     *
     * @var string
     */
    public $templatename;

    /**
     * The ID of the availablility zone for the virtual machine
     *
     * @var string
     */
    public $zoneid;

    /**
     * The name of the availability zone for the virtual machine
     *
     * @var string
     */
    public $zonename;

    /**
     * Sets tags
     *
     * @param   ResponseTagsList $tags
     * @return  VirtualMachineInstancesData
     */
    public function setTags(ResponseTagsList $tags = null)
    {
        return $this->__call(__FUNCTION__, array($tags));
    }

    /**
     * Sets security group list
     *
     * @param   SecurityGroupList $securitygroup
     * @return  VirtualMachineInstancesData
     */
    public function setSecuritygroup(SecurityGroupList $securitygroup = null)
    {
        return $this->__call(__FUNCTION__, array($securitygroup));
    }

    /**
     * Sets nic list
     *
     * @param   NicList $nic
     * @return  VirtualMachineInstancesData
     */
    public function setNic(NicList $nic = null)
    {
        return $this->__call(__FUNCTION__, array($nic));
    }

    /**
     * Sets affinity group list
     *
     * @param   AffinityGroupList $affinitygroup
     * @return  VirtualMachineInstancesData
     */
    public function setAffinitygroup(AffinityGroupList $affinitygroup = null)
    {
        return $this->__call(__FUNCTION__, array($affinitygroup));
    }

    /**
     * Sets details
     *
     * @param   VirtualDetailsData $details
     * @return  VirtualMachineInstancesData
     */
    public function setDetails(VirtualDetailsData $details = null)
    {
        return $this->__call(__FUNCTION__, array($details));
    }

}