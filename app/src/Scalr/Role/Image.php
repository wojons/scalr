<?php
namespace Scalr\Role;

use Scalr\Model\AbstractEntity;

/**
 * Role images model
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (07.08.2014)
 *
 * @Entity
 * @Table(name="role_images")
 */

class Image extends AbstractEntity
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
    public $imageId;

    /**
     * @Column(type="string")
     * @var integer
     */
    public $platform;

    /**
     * @Column(type="string")
     * @var integer
     */
    public $cloudLocation;

    /**
     * @Column(type="datetime")
     * @var \DateTime
     */
    public $dtAdded;

    public $active;

    public function validateImage()
    {

    }



}
