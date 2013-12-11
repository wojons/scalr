<?php
namespace Scalr\Service\Aws\Ec2\DataType;

use Scalr\Service\Aws\Ec2Exception;

/**
 * InstanceStatusFilterData
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    23.01.2013
 *
 * @property \Scalr\Service\Aws\Ec2\DataType\InstanceStatusFilterNameType $name
 *           A filter key name
 *
 * @property array $value
 *           An array of values
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\InstanceStatusFilterNameType getName()
 *           getName()
 *           Gets filter key name.
 *
 * @method   void __construct()
 *           __construct(\Scalr\Service\Aws\Ec2\DataType\InstanceStatusFilterNameType|string $name, string|array $value)
 *           Constructor
 *
 * @method   array getValue()
 *           getValue()
 *           Gets list of values.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\InstanceStatusFilterData setName()
 *           setName(\Scalr\Service\Aws\Ec2\DataType\InstanceStatusFilterNameType|string $name)
 *           Sets filter key name.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\InstanceStatusFilterData setValue()
 *           setName(string|array $value)
 *           Sets value or the list of the values.
 */
class InstanceStatusFilterData extends AbstractFilterData
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Ec2\DataType.AbstractFilterData::getFilterNameTypeClass()
     */
    public function getFilterNameTypeClass()
    {
        return __NAMESPACE__ . '\\InstanceStatusFilterNameType';
    }
}
