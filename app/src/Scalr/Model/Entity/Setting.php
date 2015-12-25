<?php

namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * Abstract Setting entity
 *
 * @author N.V.
 */
abstract class Setting extends AbstractEntity
{

    /**
     * Property name
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * Property value
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $value = false;

    /**
     * Sets property value
     *
     * @param   string  $value
     *
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Reset referenced fields
     */
    public function __clone()
    {
        $unref = $this->value;
        $this->value = &$unref;
    }
}
