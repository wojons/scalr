<?php

namespace Scalr\Model\Entity;

/**
 * Role global variable entity
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.4 (01.04.2015)
 *
 * @Entity
 * @Table(name="role_variables")
 */
class RoleGlobalVariable extends GlobalVariable
{

    /**
     * Role id
     *
     * @Id
     * @Column(type="integer")
     * @var integer
     */
    public $roleId;
}