<?php
namespace Scalr\Service\Aws\Ec2\DataType;

/**
 * KeyPairFilterData
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    08.02.2013
 *
 * @property \Scalr\Service\Aws\Ec2\DataType\KeyPairFilterNameType $name
 *           A filter key name
 *
 * @property array $value
 *           An array of values
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\KeyPairFilterNameType getName()
 *           getName()
 *           Gets filter key name.
 *
 * @method   void __construct()
 *           __construct(\Scalr\Service\Aws\Ec2\DataType\KeyPairFilterNameType|string $name, string|array $value)
 *           Constructor
 *
 * @method   array getValue()
 *           getValue()
 *           Gets list of values.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\KeyPairFilterData setName()
 *           setName(\Scalr\Service\Aws\Ec2\DataType\KeyPairFilterNameType|string $name)
 *           Sets filter key name.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\KeyPairFilterData setValue()
 *           setName(string|array $value)
 *           Sets value or the list of the values.
 */
class KeyPairFilterData extends AbstractFilterData
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Ec2\DataType.AbstractFilterData::getFilterNameTypeClass()
     */
    public function getFilterNameTypeClass()
    {
        return __NAMESPACE__ . '\\KeyPairFilterNameType';
    }
}
