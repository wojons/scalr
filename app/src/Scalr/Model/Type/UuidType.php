<?php
namespace Scalr\Model\Type;

/**
 * UuidType
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (07.02.2014)
 */
class UuidType extends StringType implements GeneratedValueTypeInterface
{

    protected $wh = 'UNHEX(?)';

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toDb()
     */
    public function toDb($value)
    {
        if ($value === null) return null;
        return str_replace('-', '', $value);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toPhp()
     */
    public function toPhp($value)
    {
        if ($value === null) return null;
        return preg_replace('/^(.{8})(.{4})(.{4})(.{4})(.{12})$/', "\\1-\\2-\\3-\\4-\\5", bin2hex($value));
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\GeneratedValueTypeInterface::generateValue()
     */
    public function generateValue($entity = null)
    {
        return \Scalr::GenerateUID();
    }
}