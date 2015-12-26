<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * Role images model
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.10.16 (08.12.2015)
 *
 * @Entity
 * @Table(name="role_environments")
 */
class RoleEnvironment extends AbstractEntity
{
    /**
     * RoleID
     *
     * @Id
     * @Column(type="integer")
     * @var integer
     */
    public $roleId;

    /**
     * EnvironmentID
     *
     * @Id
     * @Column(type="integer")
     * @var integer
     */
    public $envId;

}
