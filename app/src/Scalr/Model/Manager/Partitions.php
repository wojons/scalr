<?php

namespace Scalr\Model\Manager;

use Scalr\Model\AbstractEntity;
use Scalr\Exception\ModelException;
use Scalr\Model\Loader\Field;

/**
 * Partitions manager
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (09.04.2014)
 */
class Partitions
{
    /**
     * @var AbstractEntity
     */
    private $entity;

    /**
     * The name of the field in the entity
     *
     * @var Field
     */
    private $field;

    /**
     * Constructor
     *
     * @param   AbstractEntity $entity The target
     * @param   string         $field  The field name
     */
    public function __construct(AbstractEntity $entity, $field)
    {
        $this->entity = $entity;

        if (!($entity instanceof AbstractEntity)) {
            throw new \InvalidArgumentException("The first argument should be instance of the AbstractEntity class.");
        }

        $this->field = $this->entity->getIterator()->getField($field);

        if (!$this->field) {
            throw new ModelException(sprintf("Invalid field %s for entity %s", $field, get_class($this->entity)));
        }
    }

    /**
     * Creates partitions
     */
    public function create()
    {
        $db = $this->entity->db();

        $dt = new \DateTime('tomorrow');
        $end = new \DateTime('+1 month');
        $interval = new \DateInterval("P1D");

        $patritionSet = '';

        while ($dt <= $end) {
            $patritionSet .= "PARTITION p" . $dt->format('Ymd') . " VALUES LESS THAN (UNIX_TIMESTAMP('" . $dt->format('Y-m-d'). " 00:00:00')),";
            $dt->add($interval);
        }

        $this->_disableChecks();

        try {
            $this->entity->db()->Execute("
                ALTER TABLE " .  $this->entity->table() . "
                PARTITION BY RANGE(UNIX_TIMESTAMP(" . $this->field->getColumnName() . ")) (" . rtrim($patritionSet, ',') . ")
            ");
        } catch (\Exception $e) {
            $this->_enableChecks();
            throw $e;
        }

        $this->_enableChecks();
    }
}