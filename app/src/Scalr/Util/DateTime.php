<?php

class Scalr_Util_DateTime
{
    public static function convertTimeZone(DateTime $dt, $remoteTz = NULL)
    {
        if (is_null($remoteTz)) {
            $remoteTz = date_default_timezone_get();
            if (! is_string($remoteTz))
                return $dt;
        }

        if (! $remoteTz instanceof DateTimeZone)
            $remoteTz = new DateTimeZone($remoteTz);

        $dt->setTimezone($remoteTz);
        return $dt;
    }

    /**
     * Converts Time according to timezone parameter.
     *
     * @param   DateTime|string|int  $value  DateTime object or Unix Timestamp or string that represents time.
     * @param   string               $timezone  TimeZone
     * @param   string               $format  Format
     * @return  string               Returns updated time in given format.
     */
    public static function convertDateTime($value, $timezone, $format = 'M j, Y H:i:s')
    {
        if (is_integer($value)) {
            $value = "@{$value}";
        }

        if ($value instanceof DateTime) {
            $dt = $value;
        } else {
            $dt = new DateTime($value);
        }

        if ($dt && $dt->getTimestamp()) {
            if ($timezone)
                $dt = self::convertTimeZone($dt, $timezone);

            return $dt->format($format);
        } else
            return NULL;

    }

    /**
     * Converts Time according to timezone settings of current user.
     *
     * @param   DateTime|string|int  $value  DateTime object or Unix Timestamp or string that represents time.
     * @param   string               $format  Format
     * @return  string               Returns updated time in given format.
     */
    public static function convertTz($value, $format = 'M j, Y H:i:s')
    {
        $timezone = '';
        if (Scalr_UI_Request::getInstance()->getUser()) {
            $timezone = Scalr_UI_Request::getInstance()->getUser()->getSetting(Scalr_Account_User::SETTING_UI_TIMEZONE);
            if (! $timezone) {
                $timezone = 'UTC';
            }
        }

        return self::convertDateTime($value, $timezone, $format);
    }

    /**
     * Converts Time according to timezone settings of current user from UTC date.
     *
     * @param   DateTime|string|int  $value  DateTime object or Unix Timestamp or string that represents time.
     * @param   string               $format  Format
     * @return  string               Returns updated time in given format.
     */
    public static function convertTzFromUTC($value, $format = 'M j, Y H:i:s')
    {
        if ($value instanceof DateTime) {
            $dt = $value;
        } else {
            $dt = new DateTime($value, new DateTimeZone('UTC'));
        }

        return self::convertTz($dt, $format);
    }

    /**
     * @param   bool    $returnOffset optional If true return object [timezone] = GMT offset, otherwise return array of timezones
     * @return  array
     */
    public static function getTimezones($returnOffset = false)
    {
        $timezones = [];
        foreach (DateTimeZone::listIdentifiers() as $name) {
            if (preg_match('/^(America|Arctic|Asia|Atlantic|Europe|Indian|Pacific|Australia|UTC)/', $name))
                $timezones[] = $name;
        }

        sort($timezones);
        if ($returnOffset) {
            $result = [];
            foreach ($timezones as $name) {
                $result[$name] = (new DateTime(null, new DateTimeZone($name)))->getOffset();
            }

            return $result;
        } else {
            return $timezones;
        }
    }

    public static function findTimezoneByOffset($offset)
    {
        foreach (self::getTimezones(true) as $key => $name) {
            if ($key == $offset)
                return $name;
        }
    }

    /**
     * Correct time with current timezone offset
     *
     * @param   integer    $time Time to convert
     * @param   float      $tz_offset timezone offset in hours
     * @return  int        Returns unix timestamp
     */
    public static function correctTime($time = 0, $tz_offset = null)
    {
        if (!is_numeric($time)) {
            $time = strtotime($time);
        }
        if (!$time) {
            $time = time();
        }
        return (is_null($tz_offset) ? $time : $time - date('Z') + $tz_offset * 3600);
    }

    /**
     * Gets a slightly more fuzzy time string. such as: yesterday at 3:51pm
     *
     * @param  integer|string $time      Time
     * @param  integer        $tz_offset optional A timezone offset
     * @return string
     */
    public static function getFuzzyTime($time = 0, $tz_offset = null)
    {
        $time = self::correctTime($time, $tz_offset);
        $now = self::correctTime(0, $tz_offset);

        $sodTime = mktime(0, 0, 0, date('m', $time), date('d', $time), date('Y', $time));
        $sodNow = mktime(0, 0, 0, date('m', $now), date('d', $now), date('Y', $now));

        if ($sodNow == $sodTime) {
            return 'today at ' . date('g:ia', $time); // check 'today'
        } else if (($sodNow - $sodTime) <= 86400) {
            return 'yesterday at ' . date('g:ia', $time); // check 'yesterday'
        } else if (($sodNow - $sodTime) <= 432000) {
            return date('l \a\\t g:ia', $time); // give a day name if within the last 5 days
        } else if (date('Y', $now) == date('Y', $time)) {
            return date('M j \a\\t g:ia', $time); // miss off the year if it's this year
        } else {
            return date('M j, Y \a\\t g:ia', $time); // return the date as normal
        }
    }

    /**
     * Formats a time interval depending on the size according to:
     * - just now               - interval < 1 min
     * - 2 min ago              - interval < 30 mins
     * - 3:17 AM                - today
     * - Yesterday, 3:17 AM     - yesterday
     * - Jan. 10, 7:12 AM       - current year
     * - Jan. 10,2015, 7:12 AM  - less than the current year
     *
     * @param  integer|DateTime $time Time
     * @return string
     */
    public static function getIncrescentTimeInterval($value, $currentTime = null)
    {
        if (!($value instanceof DateTime)) {
            $value = new DateTime($value);
        }

        $time = $value->getTimestamp();

        if (is_null($currentTime)) {
            $now = time();
        } else {
            if (!($currentTime instanceof DateTime)) {
                $currentTime = new DateTime($currentTime);
            }
            $now = $currentTime->getTimestamp();
        }

        if ($time & $now) {
            $diff = $now - $time;
            if ($diff < 60) {
                return 'just now';
            } elseif ($diff < 1800) {
                $mins = ($diff / 60) % 60;
                return "{$mins} min ago";
            } elseif (date('Ymd', $time) == date('Ymd', $now)) {
                return date('g:i A', $time);
            } elseif (date('Ymd', $time) == date('Ymd', strtotime('yesterday', $now))) {
                return 'Yesterday, ' . date('g:i A', $time);
            } elseif (date('Y', $time) == date('Y', $now)) {
                return date('M. j, g:i A', $time);
            } else {
                return date('M. j, Y, g:i A', $time);
            }
        } else {
            return NULL;
        }
    }

    public static function getFuzzyTimeString($value)
    {
        if (is_integer($value)) {
            $value = "@{$value}";
        }

        if (!($value instanceof DateTime)) {
            $value = new DateTime($value);
        }
        $time = $value->getTimestamp();

        if ($time) {
            $now = time();
            $sodTime = mktime(0, 0, 0, date('m', $time), date('d', $time), date('Y', $time));
            $sodNow  = mktime(0, 0, 0, date('m', $now), date('d', $now), date('Y', $now));

            $diff = $sodNow - $sodTime;
            if ($sodNow == $sodTime) {// check 'today'
                return 'today at ' . Scalr_Util_DateTime::convertTz($time, 'g:ia');
            } else if ($diff <= 86400) {// check 'yesterday'
                return 'yesterday at ' . Scalr_Util_DateTime::convertTz($time, 'g:ia');
            } else if ($diff <= 604800) { //within last week
                return floor($diff/86400).' days ago';
            } else if ($diff <= 2419200) {//within last month
                $week = floor($diff/604800);
                return $week.' week'.($week>1?'s':'').' ago';
            } else if (date('Y', $now) == date('Y', $time)) {
                return Scalr_Util_DateTime::convertTz($time, 'M j \a\\t g:ia'); // miss off the year if it's this year
            } else {
                return Scalr_Util_DateTime::convertTz($time, 'M j, Y'); // return the date as normal
            }

        } else
            return NULL;
    }

    /**
     * Converts a Unix timestamp or date/time string to a human-readable
     * format, such as '1 day, 2 hours, 42 mins, and 52 secs'
     *
     * Based on the word_time() function from PG+ (http://pgplus.ewtoo.org)
     *
     * @param  integer|string $time      Timeout
     * @param  bool           $show_secs optional Should it show the seconds
     * @return string         Returns human readable timeout
     */
    public static function getHumanReadableTimeout($time = 0, $show_secs = true)
    {
        if (!is_numeric($time)) {
            $time = strtotime($time);
        }
        if ($time == 0) {
            return 'Unknown';
        } else {
            if ($time < 0) {
                $neg = 1;
                $time = 0 - $time;
            } else {
                $neg = 0;
            }

            $days = floor($time / 86400);

            $hrs = ($time / 3600) % 24;

            $mins = ($time / 60) % 60;

            $secs = $show_secs ? $time % 60 : 0;

            $timestring = '';
            if ($neg) {
                $timestring .= 'negative ';
            }
            if ($days) {
                $timestring .= "$days day" . ($days == 1 ? '' : 's');
                if ($hrs || $mins || $secs) {
                    $timestring .= ', ';
                }
            }
            if ($hrs) {
                $timestring .= "$hrs hour" . ($hrs == 1 ? '' : 's');
                if ($mins && $secs) {
                    $timestring .= ', ';
                }
                if (($mins && !$secs) || (!$mins && $secs)) {
                    $timestring .= ' and ';
                }
            }
            if ($mins) {
                $timestring .= "$mins min" . ($mins == 1 ? '' : 's');
                if ($mins && $secs) {
                    $timestring .= ', ';
                }
                if ($secs) {
                    $timestring .= ' and ';
                }
            }
            if ($secs) {
                $timestring .= "$secs sec" . ($secs == 1 ? '' : 's');
            }

            return $timestring;
        }
    }

    /**
     * Calculates difference in seconds between two dates.
     *
     * @param   DateTime|string      $value  DateTime object or string that represents time.
     * @param   bool                 $humanReadable Return number of seconds or human readable string
     * @return  string|int           Returns difference between dates.
     */
    public static function getDateTimeDiff($dateTime1, $dateTime2, $humanReadable = true)
    {
        if (!($dateTime1 instanceof DateTime)) {
            $dateTime1 = new DateTime($dateTime1);
        }
        if (!($dateTime2 instanceof DateTime)) {
            $dateTime2 = new DateTime($dateTime2);
        }

        $diff = $dateTime1->getTimestamp() - $dateTime2->getTimestamp();

        if ($humanReadable) {
            $diff = Scalr_Util_DateTime::getHumanReadableTimeout($diff);
        }

        return $diff;
    }

    /**
     * Gets the week of the year in the same way as MYSQL's YEARWEEK(date, 0) MODE 0
     *
     * @param   string    $date  The date YYYY-MM-DD
     * @return  number    Returns yearweek, for example: 201402
     */
    public static function yearweek($date)
    {
        $year = substr($date, 0, 4);

        $week = strftime('%U', strtotime($date));

        if ($week == 0) {
            return self::yearweek((--$year) . '-12-31');
        }

        return sprintf('%d%02d', $year, $week);
    }
}
