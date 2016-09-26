<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * Role images model
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (03.09.2014)
 *
 * @Entity
 * @Table(name="role_image_history")
 */
class ImageHistory extends AbstractEntity
{
    /**
     * ID
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var integer
     */
    public $id;

    /**
     * @Column(type="integer")
     * @var string
     */
    public $roleId;

    /**
     * @Column(type="string")
     * @var string
     */
    public $platform;

    /**
     * @Column(type="string")
     * @var string
     */
    public $cloudLocation;

    /**
     * @Column(type="string")
     * @var string
     */
    public $imageId;

    /**
     * @Column(type="string")
     * @var string
     */
    public $oldImageId;

    /**
     * @Column(type="datetime")
     * @var \DateTime
     */
    public $dtAdded;

    /**
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $addedById;

    /**
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $addedByEmail;

    public function __construct()
    {
        $this->dtAdded = new \DateTime();
        $this->imageId = '';
        $this->oldImageId = '';
    }
}
