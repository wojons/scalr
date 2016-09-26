<?php
namespace Scalr\Service\Aws\Ec2\DataType;

/**
 * ImageFilterData
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    23.01.2013
 *
 * @property \Scalr\Service\Aws\Ec2\DataType\ImageFilterNameType $name
 *           A filter key name
 *
 * @property array $value
 *           An array of values
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\ImageFilterNameType getName()
 *           getName()
 *           Gets filter key name.
 *
 * @method   void __construct()
 *           __construct(\Scalr\Service\Aws\Ec2\DataType\ImageFilterNameType|string $name, string|array $value)
 *           Constructor
 *
 * @method   array getValue()
 *           getValue()
 *           Gets list of values.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\ImageFilterData setName()
 *           setName(\Scalr\Service\Aws\Ec2\DataType\ImageFilterNameType|string $name)
 *           Sets filter key name.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\ImageFilterData setValue()
 *           setName(string|array $value)
 *           Sets value or the list of the values.
 */
class ImageFilterData extends AbstractFilterData
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Ec2\DataType.AbstractFilterData::getFilterNameTypeClass()
     */
    public function getFilterNameTypeClass()
    {
        return __NAMESPACE__ . '\\ImageFilterNameType';
    }
}
