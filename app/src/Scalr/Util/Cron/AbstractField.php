<?php
namespace Scalr\Util\Cron;

use DateTime;

/**
 * AbstractField
 *
 * @author    Vitaliy Demidov  <vitaliy@scalr.com>
 * @since     5.0 (16.09.2014)
 */
abstract class AbstractField implements FieldInterface
{

    /**
     * Checks if expression value and date value converge
     *
     * @param   string   $dateValue  Date value to check
     * @param   string   $value      Expression value
     * @return  boolean  Returns true if expression value and date value converge
     */
    protected function coincide($dateValue, $value)
    {
        if ($value === '*' || $dateValue == $value) {
            return true;
        } elseif ($this->isIncrementsOfRanges($value)) {
            return $this->isInIncrementsOfRanges($dateValue, $value);
        } elseif ($this->isRange($value)) {
            return $this->isInRange($dateValue, $value);
        }

        return false;
    }

    /**
     * Checks if token is a range
     *
     * @param   string $value The token from some part of an expression
     * @return  bool
     */
    protected function isRange($value)
    {
        return strpos($value, '-') !== false;
    }

    /**
     * Check if token is an increments of ranges
     *
     * @param   string  $value The token from some part of an expression
     * @return  bool    Returns true if token is an increments of ranges
     */
    protected function isIncrementsOfRanges($value)
    {
        return strpos($value, '/') !== false;
    }

    /**
     * Checks whether token is within a range
     *
     * @param   string $dateValue Date value
     * @param   string $value     Token
     * @return  bool   Returns true if token is withing a range
     */
    protected function isInRange($dateValue, $value)
    {
        $tokens = array_map('trim', explode('-', $value, 2));

        return $dateValue >= $tokens[0] && $dateValue <= $tokens[1];
    }

    /**
     * Checks whether token is within an increments of ranges
     *
     * @param   string $dateValue Date value
     * @param   string $value     Token
     * @return  bool   Returns true if token is withing an increments of ranges
     */
    protected function isInIncrementsOfRanges($dateValue, $value)
    {
        $tokens = array_map('trim', explode('/', $value, 2));

        $step = isset($tokens[1]) ? intval($tokens[1]) : 1;

        if ($step < 1) {
            $step = 1;
        }

        if ($tokens[0] == '*' || $tokens[0] === '0') {
            return ((int) $dateValue % $step) == 0;
        }

        $range = explode('-', $tokens[0], 2);

        $from = $range[0];

        $to = isset($range[1]) ? $range[1] : $dateValue;

        if ($dateValue < $from || $dateValue > $to) {
            return false;
        }

        for ($i = $from; $i <= $to; $i += $step) {
            if ($i == $dateValue) {
                return true;
            }
        }

        return false;
    }
}