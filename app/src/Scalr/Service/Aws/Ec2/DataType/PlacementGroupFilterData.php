<?php
namespace Scalr\Service\Aws\Ec2\DataType;

/**
 * PlacementGroupFilterData
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    31.01.2013
 *
 * @property \Scalr\Service\Aws\Ec2\DataType\PlacementGroupFilterNameType $name
 *           A filter key name
 *
 * @property array $value
 *           An array of values
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\PlacementGroupFilterNameType getName()
 *           getName()
 *           Gets filter key name.
 *
 * @method   void __construct()
 *           __construct(\Scalr\Service\Aws\Ec2\DataType\PlacementGroupFilterNameType|string $name, string|array $value)
 *           Constructor
 *
 * @method   array getValue()
 *           getValue()
 *           Gets list of values.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\PlacementGroupFilterData setName()
 *           setName(\Scalr\Service\Aws\Ec2\DataType\PlacementGroupFilterNameType|string $name)
 *           Sets filter key name.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\PlacementGroupFilterData setValue()
 *           setName(string|array $value)
 *           Sets value or the list of the values.
 */
class PlacementGroupFilterData extends AbstractFilterData
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Ec2\DataType.AbstractFilterData::getFilterNameTypeClass()
     */
    public function getFilterNameTypeClass()
    {
        return __NAMESPACE__ . '\\PlacementGroupFilterNameType';
    }
}
