<?php
namespace Scalr\Util\Cron;

use DateTime, DateTimeZone;
use InvalidArgumentException;

/**
 * Cron expression
 *
 * @author    Vitaliy Demidov  <vitaliy@scalr.com>
 * @since     5.0 (15.09.2014)
 */
class CronExpression
{
    /**
     * Timezone expression corresponds to
     *
     * @var DateTimeZone
     */
    private $timezone;

    /**
     * Crontab expression
     *
     * @var   string
     */
    private $expression;

    /**
     * Each part of the cron expression
     *
     * @var array
     */
    private $parts;

    /**
     * Fields cache
     *
     * @var array
     */
    private static $fields;

    /**
     * Constructor
     *
     * @param   string              $expression  Cron expression
     * @param   string|DateTimeZone $timezone    optional Timezone expression corresponds to
     */
    public function __construct($expression, $timezone = null)
    {
        $this->setTimezone($timezone);
        $this->setExpression($expression);
    }

    /**
     * Gets the field object for the specified number of the part of the cron expression
     *
     * @param   int     $number  The number of the part of the cron expression
     * @return  AbstractField    Returns field
     * @throws  InvalidArgumentException
     */
    public static function field($number)
    {
        if ($number < 0 || $number > 4) {
            throw new InvalidArgumentException(sprintf('Invalid number %d. Allowed range: 0 - 4', $number));
        }

        if (!isset(static::$fields[$number])) {
            $class = __NAMESPACE__ . '\\Field' . $number;
            static::$fields[$number] = new $class;
        }

        return static::$fields[$number];
    }

    /**
     * Sets expression
     *
     * @param   string        $expression Cron expression
     * @return  CronExpression
     */
    public function setExpression($expression)
    {
        $this->expression = $expression;

        $this->parts = preg_split('/\s/', $expression, 5, PREG_SPLIT_NO_EMPTY);

        if (count($this->parts) < 5) {
            throw new InvalidArgumentException(sprintf("Invalid cron expression: %s", $expression));
        }

        foreach ($this->parts as $position => $value) {
            $this->setPart($position, $value);
        }

        return $this;
    }

    /**
     * Sets a part of the cron expression
     *
     * @param   int      $position  Part number in expression
     * @param   string   $value     The value
     * @throws  InvalidArgumentException
     */
    private function setPart($position, $value)
    {
        if (!static::field($position)->validate($value)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid cron expression part at position %d: %s", $position, $value
            ));
        }

        $this->parts[$position] = $value;
    }


    /**
     * Sets timezone expression corresponds to
     *
     * @param   string|DateTimeZone  $timezone optional A timezone (system is used by default)
     * @return  \Scalr\Util\Cron\CronExpression
     */
    public function setTimezone($timezone = null)
    {
        if (!($timezone instanceof DateTimeZone)) {
            $this->timezone = new DateTimeZone($timezone ?: date_default_timezone_get());
        } else {
            $this->timezone = clone $timezone;
        }

        return $this;
    }

    /**
     * Gets timezone expression corresponds to
     *
     * @return   DateTimeZone Returns timezone
     */
    public function getTimezone()
    {
        return $this->timezone;
    }

    /**
     * Determine if the cron is due to run
     *
     * @param   string|DateTime $currentTime  Current time in the system timezone
     * @return  boolean Returns true if cron is due to run
     */
    public function isDue($currentTime = null)
    {
        if (!($currentTime instanceof DateTime)) {
            $dt = new DateTime($currentTime ?: 'now');
        } else {
            $dt = clone $currentTime;
        }

        //If expression's timezone does not match system we should update timezone
        if ($dt->getTimezone()->getName() !== $this->timezone->getName()) {
            $dt->setTimezone($this->timezone);
        }

        $ret = true;

        foreach ($this->parts as $position => $part) {
            if ($part === '*' || $part === null) {
                continue;
            }

            $field = self::field($position);

            if (strpos($part, ',') === false) {
                //Singular token
                $ret = $ret && $field->match($dt, $part);
            } else {
                //The set of the tokens
                $coincided = false;
                foreach (array_map('trim', explode(',', $part)) as $token) {
                    if ($field->match($dt, $token)) {
                        $coincided = true;
                        break;
                    }
                }
                $ret = $ret && $coincided;
            }

            if (!$ret) break;
        }

        return $ret;
    }

    /**
     * Gets expression as string
     *
     * @return  string
     */
    public function __toString()
    {
        return join(" ", $this->parts);
    }
}