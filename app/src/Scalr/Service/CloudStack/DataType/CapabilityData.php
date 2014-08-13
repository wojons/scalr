<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * CapabilityData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class CapabilityData extends AbstractDataType
{

    /**
     * True if regular user is allowed to create projects
     *
     * @var string
     */
    public $allowusercreateprojects;

    /**
     * Time interval (in seconds) to reset api count
     *
     * @var string
     */
    public $apilimitinterval;

    /**
     * Max allowed number of api requests within the specified interval
     *
     * @var string
     */
    public $apilimitmax;

    /**
     * Version of the cloud stack
     *
     * @var string
     */
    public $cloudstackversion;

    /**
     * Maximum size that can be specified when create disk from disk offering with custom size
     *
     * @var string
     */
    public $customdiskofferingmaxsize;

    /**
     * True if snapshot is supported for KVM host, false otherwise
     *
     * @var string
     */
    public $kvmsnapshotenabled;

    /**
     * If invitation confirmation is required when add account to project
     *
     * @var string
     */
    public $projectinviterequired;

    /**
     * True if region wide secondary is enabled, false otherwise
     *
     * @var string
     */
    public $regionsecondaryenabled;

    /**
     * True if security groups support is enabled, false otherwise
     *
     * @var string
     */
    public $securitygroupsenabled;

    /**
     * True if region supports elastic load balancer on basic zones
     *
     * @var string
     */
    public $supportELB;

    /**
     * True if user and domain admins can set templates to be shared, false otherwise
     *
     * @var string
     */
    public $userpublictemplateenabled;

}