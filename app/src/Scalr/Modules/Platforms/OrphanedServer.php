<?php

namespace Scalr\Modules\Platforms;

/**
 * Orphaned cloud server object
 *
 * @author Constantine Karancevych <c.karnacevych@scalr.com>
 */
class OrphanedServer implements \ArrayAccess
{
    /**
     * Cloud server identifier
     *
     * @var string
     */
    public $cloudServerId;

    /**
     * Cloud instance type
     *
     * @var string
     */
    public $instanceType;

    /**
     * Image identifier
     *
     * @var string
     */
    public $imageId;

    /**
     * Image hash
     *
     * @var string
     */
    public $imageHash;

    /**
     * Image name
     *
     * @var string
     */
    public $imageName;

    /**
     * Server status
     *
     * @var string
     */
    public $status;

    /**
     * The time the server was launched in UTC
     *
     * @var \DateTime
     */
    public $launchTime;

    /**
     * Private IP address
     *
     * @var string
     */
    public $privateIp;

    /**
     * Public IP address
     *
     * @var string
     */
    public $publicIp;

    /**
     * Key name
     *
     * @var string
     */
    public $keyPairName;

    /**
     * VPC identifier
     *
     * @var string
     */
    public $vpcId;

    /**
     * Subnet identifier
     *
     * @var string
     */
    public $subnetId;

    /**
     * Architecture
     *
     * @var string
     */
    public $architecture;

    /**
     * Security groups
     *
     * @var array
     */
    public $securityGroups;

    /**
     * Associated tags
     *
     * @var array
     */
    public $tags;

    /**
     * Constructor
     *
     * @param string    $cloudServerId           Cloud server identifier
     * @param string    $instanceType            Cloud instance type
     * @param string    $imageId                 Image identifier
     * @param string    $status                  Server status
     * @param \DateTime $launchTime              The time the server was launched in UTC
     * @param string    $privateIp               Private IP address
     * @param string    $publicIp       optional Public IP address
     * @param string    $keyPairName    optional Key name
     * @param string    $vpcId          optional VPC identifier
     * @param string    $subnetId       optional Subnet identifier
     * @param string    $architecture   optional Architecture
     * @param array     $securityGroups optional Security groups
     * @param array     $tags           optional Associated tags
     */
    public function __construct(
        $cloudServerId,
        $instanceType,
        $imageId,
        $status,
        \DateTime $launchTime,
        $privateIp,
        $publicIp = null,
        $keyPairName = null,
        $vpcId = null,
        $subnetId = null,
        $architecture = null,
        array $securityGroups = [],
        array $tags = []
    ) {
        $this->cloudServerId  = $cloudServerId;
        $this->instanceType   = $instanceType;
        $this->imageId        = $imageId;
        $this->status         = $status;
        $this->privateIp      = $privateIp;
        $this->publicIp       = $publicIp;
        $this->keyPairName    = $keyPairName;
        $this->vpcId          = $vpcId;
        $this->subnetId       = $subnetId;
        $this->architecture   = $architecture;
        $this->securityGroups = $securityGroups;
        $this->tags           = $tags;
        $this->launchTime     = $launchTime;
    }

    /**
     * Protection against setting bad property
     *
     * @throws \RuntimeException
     */
    public function __set($property, $value)
    {
        throw new \RuntimeException("Property '$property' doesn't exist!");
    }

    /**
     * {@inheritdoc}
     * @see \ArrayAccess::offsetExists()
     */
    public function offsetExists($property)
    {
        return property_exists($this, $property);
    }

    /**
     * {@inheritdoc}
     * @see \ArrayAccess::offsetGet()
     */
    public function offsetGet($property)
    {
        if (property_exists($this, $property)) {
            return $this->$property;
        } else {
            throw new \RuntimeException("Property '$property' doesn't exist!");
        }
        return null;
    }

    /**
     * {@inheritdoc}
     * @see \ArrayAccess::offsetSet()
     */
    public function offsetSet($property, $value)
    {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        } else {
            throw new \RuntimeException("Property '$property' doesn't exist!");
        }
    }

    /**
     * {@inheritdoc}
     * @see \ArrayAccess::offsetUnset()
     */
    public function offsetUnset($property)
    {
        if (property_exists($this, $property)) {
            $this->$property = null;
        } else {
            throw new \RuntimeException("Property '$property' doesn't exist!");
        }
    }
}
