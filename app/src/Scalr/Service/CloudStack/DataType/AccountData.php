<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * AccountData
 *
 * @property  \Scalr\Service\CloudStack\DataType\UserList       $user
 * The list of users associated with account
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class AccountData extends AbstractDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('user');

    /**
     * The id of the account
     *
     * @var string
     */
    public $id;

    /**
     * Details for the account
     *
     * @var string
     */
    public $accountdetails;

    /**
     * Account type (admin, domain-admin, user)
     *
     * @var string
     */
    public $accounttype;

    /**
     * The total number of cpu cores available to be created for this account
     *
     * @var string
     */
    public $cpuavailable;

    /**
     * The total number of cpu cores the account can own
     *
     * @var string
     */
    public $cpulimit;

    /**
     * The total number of cpu cores owned by account
     *
     * @var string
     */
    public $cputotal;

    /**
     * The default zone of the account
     *
     * @var string
     */
    public $defaultzoneid;

    /**
     * Name of the Domain the account belongs too
     *
     * @var string
     */
    public $domain;

    /**
     * Id of the Domain the account belongs too
     *
     * @var string
     */
    public $domainid;

    /**
     * The total number of public ip addresses available for this account to acquire
     *
     * @var string
     */
    public $ipavailable;

    /**
     * The total number of public ip addresses this account can acquire
     *
     * @var string
     */
    public $iplimit;

    /**
     * The total number of public ip addresses allocated for this account
     *
     * @var string
     */
    public $iptotal;

    /**
     * True if the account requires cleanup
     *
     * @var string
     */
    public $iscleanuprequired;

    /**
     * True if account is default, false otherwise
     *
     * @var string
     */
    public $isdefault;

    /**
     * The total memory (in MB) available to be created for this account
     *
     * @var string
     */
    public $memoryavailable;

    /**
     * The total memory (in MB) the account can own
     *
     * @var string
     */
    public $memorylimit;

    /**
     * The total memory (in MB) owned by account
     *
     * @var string
     */
    public $memorytotal;

    /**
     * The name of the account
     *
     * @var string
     */
    public $name;

    /**
     * The total number of networks available to be created for this account
     *
     * @var string
     */
    public $networkavailable;

    /**
     * The network domain
     *
     * @var string
     */
    public $networkdomain;

    /**
     * The total number of networks the account can own
     *
     * @var string
     */
    public $networklimit;

    /**
     * The total number of networks owned by account
     *
     * @var string
     */
    public $networktotal;

    /**
     * The total primary storage space (in GiB) available to be used for this account
     *
     * @var string
     */
    public $primarystorageavailable;

    /**
     * The total primary storage space (in GiB) the account can own
     *
     * @var string
     */
    public $primarystoragelimit;

    /**
     * The total primary storage space (in GiB) owned by account
     *
     * @var string
     */
    public $primarystoragetotal;

    /**
     * The total number of projects available for administration by this account
     *
     * @var string
     */
    public $projectavailable;

    /**
     * The total number of projects the account can own
     *
     * @var string
     */
    public $projectlimit;

    /**
     * The total number of projects being administrated by this account
     *
     * @var string
     */
    public $projecttotal;

    /**
     * The total number of network traffic bytes received
     *
     * @var string
     */
    public $receivedbytes;

    /**
     * The total secondary storage space (in GiB) available to be used for this account
     *
     * @var string
     */
    public $secondarystorageavailable;

    /**
     * The total secondary storage space (in GiB) the account can own
     *
     * @var string
     */
    public $secondarystoragelimit;

    /**
     * The total secondary storage space (in GiB) owned by account
     *
     * @var string
     */
    public $secondarystoragetotal;

    /**
     * The total number of network traffic bytes sent
     *
     * @var string
     */
    public $sentbytes;

    /**
     * The total number of snapshots available for this account
     *
     * @var string
     */
    public $snapshotavailable;

    /**
     * The total number of snapshots which can be stored by this account
     *
     * @var string
     */
    public $snapshotlimit;

    /**
     * The total number of snapshots stored by this account
     *
     * @var string
     */
    public $snapshottotal;

    /**
     * The state of the account
     *
     * @var string
     */
    public $state;

    /**
     * The total number of templates available to be created by this account
     *
     * @var string
     */
    public $templateavailable;

    /**
     * The total number of templates which can be created by this account
     *
     * @var string
     */
    public $templatelimit;

    /**
     * The total number of templates which have been created by this account
     *
     * @var string
     */
    public $templatetotal;

    /**
     * The total number of virtual machines available for this account to acquire
     *
     * @var string
     */
    public $vmavailable;

    /**
     * The total number of virtual machines that can be deployed by this account
     *
     * @var string
     */
    public $vmlimit;

    /**
     * The total number of virtual machines running for this account
     *
     * @var string
     */
    public $vmrunning;

    /**
     * The total number of virtual machines stopped for this account
     *
     * @var string
     */
    public $vmstopped;

    /**
     * The total number of virtual machines deployed by this account
     *
     * @var string
     */
    public $vmtotal;

    /**
     * The total volume available for this account
     *
     * @var string
     */
    public $volumeavailable;

    /**
     * The total volume which can be used by this account
     *
     * @var string
     */
    public $volumelimit;

    /**
     * The total volume being used by this account
     *
     * @var string
     */
    public $volumetotal;

    /**
     * The total number of vpcs available to be created for this account
     *
     * @var string
     */
    public $vpcavailable;

    /**
     * The total number of vpcs the account can own
     *
     * @var string
     */
    public $vpclimit;

    /**
     * The total number of vpcs owned by account
     *
     * @var string
     */
    public $vpctotal;

    /**
     * Sets user
     *
     * @param   UserList $user
     * @return  AccountData
     */
    public function setUser(UserList $user = null)
    {
        return $this->__call(__FUNCTION__, array($user));
    }

}