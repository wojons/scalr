<?php
namespace Scalr\Util\Cron;

use DateTime;

/**
 * Hour Field
 *
 * Allowed values: 0-23
 *
 * @author    Vitaliy Demidov  <vitaliy@scalr.com>
 * @since     5.0 (16.09.2014)
 */
class Field1 extends AbstractField
{

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Cron\FieldInterface::match()
     */
    public function match(DateTime $time, $value)
    {
        return $this->coincide($time->format('G'), $value);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Cron\FieldInterface::validate()
     */
    public function validate($value)
    {
        return (bool) preg_match('/^[\*,\/\d-]+$/', $value);
    }
}