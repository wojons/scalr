<?php
namespace Scalr\Service\Aws\Ec2\DataType;

/**
 * AddressFilterData
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    29.01.2013
 *
 * @property \Scalr\Service\Aws\Ec2\DataType\AddressFilterNameType $name
 *           A filter key name
 *
 * @property array $value
 *           An array of values
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\AddressFilterNameType getName()
 *           getName()
 *           Gets filter key name.
 *
 * @method   void __construct()
 *           __construct(\Scalr\Service\Aws\Ec2\DataType\AddressFilterNameType|string $name, string|array $value)
 *           Constructor
 *
 * @method   array getValue()
 *           getValue()
 *           Gets list of values.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\AddressFilterData setName()
 *           setName(\Scalr\Service\Aws\Ec2\DataType\AddressFilterNameType|string $name)
 *           Sets filter key name.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\AddressFilterData setValue()
 *           setName(string|array $value)
 *           Sets value or the list of the values.
 */
class AddressFilterData extends AbstractFilterData
{

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Ec2\DataType.AbstractFilterData::getFilterNameTypeClass()
     */
    public function getFilterNameTypeClass()
    {
        return __NAMESPACE__ . '\\AddressFilterNameType';
    }
}