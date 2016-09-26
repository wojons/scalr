<?php

namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * AclRole entity
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since  5.11.18 (5.03.2016)
 *
 * @Entity
 * @Table(name="acl_account_roles")
 */
class AclRole extends AbstractEntity
{

    /**
     * Regex for ACL Role id validation
     */
    const ACL_ROLE_ID_REGEXP = '[0-9a-f]{20}';

    /**
     * ACL Role unique id
     *
     * @Id
     * @GeneratedValue("CUSTOM")
     * @Column(type="accountRoleId")
     * @var string
     */
    public $accountRoleId;

    /**
     * Identifier of the account which ACL Role corresponds to
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $accountId;

    /**
     * Base ACL Role to override
     *
     * @Column(name="role_id",type="integer")
     * @var int
     */
    public $baseRoleId;

    /**
     * ACL Role name
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $name;

    /**
     * ACL Role color
     *
     * @Column(type="integer")
     * @var int
     */
    public $color = 0;

    /**
     * Whether this role is created automatically
     * during initialization
     *
     * @Column(type="boolean")
     * @var bool
     */
    public $isAutomatic = false;

}