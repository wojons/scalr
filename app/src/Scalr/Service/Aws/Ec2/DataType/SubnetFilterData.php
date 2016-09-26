<?php
namespace Scalr\Service\Aws\Ec2\DataType;

/**
 * SubnetFilterData
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    31.01.2013
 *
 * @property \Scalr\Service\Aws\Ec2\DataType\SubnetFilterNameType $name
 *           A filter key name
 *
 * @property array $value
 *           An array of values
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\SubnetFilterNameType getName()
 *           getName()
 *           Gets filter key name.
 *
 * @method   void __construct()
 *           __construct(\Scalr\Service\Aws\Ec2\DataType\SubnetFilterNameType|string $name, string|array $value)
 *           Constructor
 *
 * @method   array getValue()
 *           getValue()
 *           Gets list of values.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\SubnetFilterData setName()
 *           setName(\Scalr\Service\Aws\Ec2\DataType\SubnetFilterNameType|string $name)
 *           Sets filter key name.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\SubnetFilterData setValue()
 *           setName(string|array $value)
 *           Sets value or the list of the values.
 */
class SubnetFilterData extends AbstractFilterData
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Ec2\DataType.AbstractFilterData::getFilterNameTypeClass()
     */
    public function getFilterNameTypeClass()
    {
        return __NAMESPACE__ . '\\SubnetFilterNameType';
    }
}
