<?php

namespace Scalr\Model\Entity;

/**
 * Farm Global Variable entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="farm_variables")
 */
class FarmGlobalVariable extends GlobalVariable
{

    /**
     * Farm id
     *
     * @Id
     * @Column(type="integer")
     * @var integer
     */
    public $farmId;
}