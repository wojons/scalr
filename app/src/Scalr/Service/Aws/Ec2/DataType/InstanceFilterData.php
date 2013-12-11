<?php
namespace Scalr\Service\Aws\Ec2\DataType;

use Scalr\Service\Aws\Ec2Exception;

/**
 * InstanceFilterData
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    23.01.2013
 *
 * @property \Scalr\Service\Aws\Ec2\DataType\InstanceFilterNameType $name
 *           A filter key name
 *
 * @property array $value
 *           An array of values
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\InstanceFilterNameType getName()
 *           getName()
 *           Gets filter key name.
 *
 * @method   void __construct()
 *           __construct(\Scalr\Service\Aws\Ec2\DataType\InstanceFilterNameType|string $name, string|array $value)
 *           Constructor
 *
 * @method   array getValue()
 *           getValue()
 *           Gets list of values.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\InstanceFilterData setName()
 *           setName(\Scalr\Service\Aws\Ec2\DataType\InstanceFilterNameType|string $name)
 *           Sets filter key name.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\InstanceFilterData setValue()
 *           setName(string|array $value)
 *           Sets value or the list of the values.
 */
class InstanceFilterData extends AbstractFilterData
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Ec2\DataType.AbstractFilterData::getFilterNameTypeClass()
     */
    public function getFilterNameTypeClass()
    {
        return __NAMESPACE__ . '\\InstanceFilterNameType';
    }
}
