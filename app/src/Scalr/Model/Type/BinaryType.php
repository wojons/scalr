<?php
namespace Scalr\Model\Type;

use Scalr\Model\Objects\BinaryStream;

/**
 * BinaryType
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (12.03.2014)
 */
class BinaryType extends StringType
{

    protected $wh = 'UNHEX(?)';

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toDb()
     */
    public function toDb($value)
    {
        if ($value === null) {
            return null;
        }

        //Internal representation allows binary string instead of BinaryStream object

        return $value instanceof BinaryStream ? $value->hex() : bin2hex((binary)$value);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toPhp()
     */
    public function toPhp($value)
    {
        if (null === $value) {
            return null;
        }

        if (!($value instanceof BinaryStream)) {
            $value = new BinaryStream((binary)$value);
        }

        return $value;
    }
}