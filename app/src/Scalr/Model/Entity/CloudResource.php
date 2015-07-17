<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;
use Scalr\Exception\ScalrException;

/**
 * CloudResource entity
 * @Table(name="farm_role_cloud_services")
 */
class CloudResource extends AbstractEntity
{

    /* Amazon Services */
    const TYPE_AWS_ELB = 'aws_elb';
    const TYPE_AWS_RDS = 'aws_rds';
    /* GCE Services */
    const TYPE_GCE_LB  = 'gce_lb';


    /**
     * The identifier of the cloud resource
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $id;

    /**
     * The type
     *
     * @Column(type="string")
     * @var string
     */
    public $type;

    /**
     * The identifier of the client's environment
     *
     * @Id
     * @Column(type="integer")
     * @var int
     */
    public $envId;

    /**
     * The identifier of the farm
     *
     * @Column(type="integer")
     * @var int
     */
    public $farmId;

    /**
     * The identifier of the farm role
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $farmRoleId;

    /**
     * Cloud platform
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $platform;

    /**
     * The cloud location
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $cloudLocation;


    /**
     * Constructor
     */
    public function __construct()
    {

    }
}