<?php

namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * GlobalVariable entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="variables")
 */
class GlobalVariable extends AbstractEntity
{

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
     * Variable's format
     *
     * @Column(type="string")
     * @var string
     */
    public $format;

    /**
     * Flag final
     *
     * @Column(name="flag_final",type="integer")
     * @var integer
     */
    public $final;

    /**
     * Required scope
     *
     * @Column(name="flag_required",type="string")
     * @var string
     */
    public $required;

    /**
     * Flag hidden
     *
     * @Column(name="flag_hidden",type="integer")
     * @var integer
     */
    public $hidden;
}