<?php

namespace Scalr\Model\Type;
use DateTime;
use DateTimeZone;

/**
 * UTCDatetimeNearMinute Type
 * Date and time type rounded up to a minute
 *
 * @author N.V.
 */
class UTCDatetimeNearMinuteType extends UTCDatetimeType
{

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toDb()
     */
    public function toDb($value)
    {
        if ($value === null) return null;
        if ($value instanceof DateTime) {
            return $value->format('Y-m-d H:i:00');
        }
        return (string) $value;
    }
}