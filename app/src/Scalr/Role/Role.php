<?php
namespace Scalr\Role;

use Scalr\Model\AbstractEntity;

/**
 * Role model
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (23.07.2014)
 *
 * @Entity
 * @Table(name="roles")
 */

class Role extends AbstractEntity
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
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * @Column(type="string")
     * @var string
     */
    public $description;

    /**
     * @Column(type="integer",name="client_id",nullable=true)
     * @var integer
     */
    public $accountId;

    /**
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $envId;

    /**
     * Nullable = ?
     *
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $catId;


    /**
     * @param $name Role name
     * @return bool
     */
    public static function validateName($name)
    {
        return !!preg_match("/^[A-Za-z0-9]+[A-Za-z0-9-]*[A-Za-z0-9]+$/si", $name);
    }
}
