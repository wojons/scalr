<?php
namespace Scalr\Util\Cron;

use DateTime;

/**
 * Day-of-week Field
 *
 * Allowed values: (0 to 6 are Sunday to Saturday; 7 is Sunday, the same as 0)
 *
 * @author    Vitaliy Demidov  <vitaliy@scalr.com>
 * @since     5.0 (16.09.2014)
 */
class Field4 extends AbstractField
{

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Cron\FieldInterface::match()
     */
    public function match(DateTime $time, $value)
    {
        $dow = $time->format('N');

        if ($dow == 7 && $this->coincide('0', $value)) {
            return true;
        }

        return $this->coincide($dow, $value);
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