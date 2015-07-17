<?php

namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * Role global variable entity
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.4 (01.04.2015)
 *
 * @Entity
 * @Table(name="role_variables")
 */
class RoleGlobalVariable extends AbstractEntity
{

    /**
     * Role id
     *
     * @Id
     * @Column(type="integer")
     * @var integer
     */
    public $roleId;

    /**
     * Variable's name
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * Variable's value
     *
     * @Column(type="encrypted")
     * @var string
     */
    public $value;

    /**
     * Variable category
     *
     * @Column(type="string")
     *
     * @var string
     */
    public $category;

    /**
     * Validator value
     *
     * @Column(type="string")
     * @var string
     */
    public $validator;

    /**
     * Format value
     *
     * @Column(type="string")
     * @var string
     */
    public $format;

    /**
     * Flag final
     *
     * @Column(type="integer")
     * @var integer
     */
    public $flagFinal;

    /**
     * Required scope
     *
     * @Column(type="string")
     * @var string
     */
    public $flagRequired;

    /**
     * Flag hidden
     *
     * @Column(type="integer")
     * @var integer
     */
    public $flagHidden;

}