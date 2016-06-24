<?php

namespace Scalr\Model\Entity\Account;

use Scalr\Model\AbstractEntity;

/**
 * EnvironmentProperty entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="client_environment_properties")
 */
class EnvironmentProperty extends AbstractEntity
{
    const SETTING_CC_ID    = 'cc_id';

    /**
     * The identifier of the client's environment
     *
     * @Id
     * @Column(type="integer")
     * @var integer
     */
    public $envId;

    /**
     * The property name
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * The property group name
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $group = '';

    /**
     * The property value
     *
     * @Column(type="string")
     * @var string
     */
    public $value;

    /**
     * The property cloud
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $cloud;
}