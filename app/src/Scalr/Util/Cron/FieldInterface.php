<?php
namespace Scalr\Util\Cron;

use DateTime;

/**
 * FieldInterface
 *
 * @author    Vitaliy Demidov  <vitaliy@scalr.com>
 * @since     5.0 (16.09.2014)
 */
interface FieldInterface
{
    /**
     * Checks if the field matches curent time
     *
     * @param   DateTime $time  Time to match in the timezone expression corresponds to
     * @param   string   $value Cron expression value from certain position
     * @return  boolean  Returns true if on success
     */
    public function match(DateTime $time, $value);

    /**
     * Validates expression
     *
     * @param   string  $value Cron expression value
     * @return  boolean Returns true if expression is valid or false otherwise
     */
    public function validate($value);
}