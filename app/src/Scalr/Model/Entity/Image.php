<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * Image entity
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (23.05.2014)
 *
 * @Entity
 * @Table(name="images")
 */
class Image extends AbstractEntity
{
    const STATUS_ACTIVE = 'active';

    const SOURCE_MANUAL = 'Manual';
    const SOURCE_BUNDLE_TASK = 'BundleTask';

    /**
     * Image ID
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $id;

    /**
     * @Id
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $envId;

    /**
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $bundleTaskId;

    /**
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $platform;

    /**
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $cloudLocation;

    /**
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $osFamily;

    /**
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $osVersion;

    /**
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $osName;

    /**
     * @Column(type="datetime",nullable=true)
     * @var \DateTime
     */
    public $dtAdded;

    /**
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $createdById;

    /**
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $createdByEmail;

    /**
     * @Column(type="string")
     * @var string
     */
    public $architecture;

    /**
     * @Column(type="integer")
     * @var integer
     */
    public $isDeprecated;

    /**
     * @Column(type="string")
     * @var string
     */
    public $source;

    /**
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $type;

    /**
     * @Column(type="string")
     * @var string
     */
    public $status;

    /**
     * @Column(type="string")
     * @var string
     */
    public $agentVersion;

    public function __construct()
    {
        // first records don't have dtAdded, we keep it null
        $this->dtAdded = new \DateTime();
    }

    public function save()
    {
        if ($this->platform == \SERVER_PLATFORMS::GCE)
            $this->cloudLocation = ''; // image on GCE doesn't require cloudLocation

        parent::save();
    }

    public function isUsed()
    {
        return !!$this->db()->GetOne('SELECT EXISTS(SELECT 1 FROM role_images WHERE image_id = ? AND platform = ? AND cloud_location = ?)', [$this->id, $this->platform, $this->cloudLocation]);
    }
}
