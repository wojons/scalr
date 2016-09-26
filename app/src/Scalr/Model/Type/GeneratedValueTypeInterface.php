<?php
namespace Scalr\Model\Type;

use Scalr\Model\AbstractEntity;

/**
 * GeneratedValueTypeInterface
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.4 (17.02.2015)
 */
interface GeneratedValueTypeInterface
{
    /**
     * Generates value of this type
     *
     * @param   AbstractEntity  $entity optional The entity which field's auto generated value may be based on
     * @return  mixed Returns auto-generated value
     */
    public function generateValue($entity = null);
}