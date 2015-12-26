<?php

namespace Scalr\Model\Entity;

/**
 * Role Property entity
 *
 * @author Igor Vodiasov <invar@scalr.com>
 *
 * @Entity
 * @Table(name="role_properties")
 */
class RoleProperty extends Setting
{
    /**
     * Role ID
     *
     * @Id
     * @Column(type="integer")
     * @var integer
     */
    public $roleId;
}
