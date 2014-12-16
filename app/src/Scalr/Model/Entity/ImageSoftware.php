<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * Image software entity
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (04.11.2014)
 *
 * @Entity
 * @Table(name="image_software")
 */
class ImageSoftware extends AbstractEntity
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
     * Image hash
     *
     * @Column(type="uuid")
     * @var string
     */
    public $imageHash;

    /**
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $version;
}
