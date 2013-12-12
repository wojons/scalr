<?php
namespace Scalr\Service\Aws\Ec2\DataType;

use Scalr\Service\Aws\Ec2Exception;

/**
 * RouteTableFilterData
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    05.04.2013
 *
 * @property \Scalr\Service\Aws\Ec2\DataType\RouteTableFilterNameType $name
 *           A filter key name
 *
 * @property array $value
 *           An array of values
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\RouteTableFilterNameType getName()
 *           getName()
 *           Gets filter key name.
 *
 * @method   void __construct()
 *           __construct(\Scalr\Service\Aws\Ec2\DataType\RouteTableFilterNameType|string $name, string|array $value)
 *           Constructor
 *
 * @method   array getValue()
 *           getValue()
 *           Gets list of values.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\RouteTableFilterData setName()
 *           setName(\Scalr\Service\Aws\Ec2\DataType\RouteTableFilterNameType|string $name)
 *           Sets filter key name.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\RouteTableFilterData setValue()
 *           setName(string|array $value)
 *           Sets value or the list of the values.
 */
class RouteTableFilterData extends AbstractFilterData
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Ec2\DataType.AbstractFilterData::getFilterNameTypeClass()
     */
    public function getFilterNameTypeClass()
    {
        return __NAMESPACE__ . '\\RouteTableFilterNameType';
    }
}
