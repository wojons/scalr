<?php
namespace Scalr\Util\Cron;

use DateTime;

/**
 * Minute Field
 *
 * Allowed values: 0-59
 *
 * @author    Vitaliy Demidov  <vitaliy@scalr.com>
 * @since     5.0 (16.09.2014)
 */
class Field0 extends AbstractField
{

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Cron\FieldInterface::match()
     */
    public function match(DateTime $time, $value)
    {
        return $this->coincide($time->format('i'), $value);
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