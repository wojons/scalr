<?php
namespace Scalr\Service\Aws\Ec2\DataType;

use Scalr\Service\Aws\Ec2Exception;

/**
 * InternetGatewayFilterData
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    03.04.2013
 *
 * @property \Scalr\Service\Aws\Ec2\DataType\InternetGatewayFilterNameType $name
 *           A filter key name
 *
 * @property array $value
 *           An array of values
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\InternetGatewayFilterNameType getName()
 *           getName()
 *           Gets filter key name.
 *
 * @method   void __construct()
 *           __construct(\Scalr\Service\Aws\Ec2\DataType\InternetGatewayFilterNameType|string $name, string|array $value)
 *           Constructor
 *
 * @method   array getValue()
 *           getValue()
 *           Gets list of values.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\InternetGatewayFilterData setName()
 *           setName(\Scalr\Service\Aws\Ec2\DataType\InternetGatewayFilterNameType|string $name)
 *           Sets filter key name.
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\InternetGatewayFilterData setValue()
 *           setName(string|array $value)
 *           Sets value or the list of the values.
 */
class InternetGatewayFilterData extends AbstractFilterData
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Ec2\DataType.AbstractFilterData::getFilterNameTypeClass()
     */
    public function getFilterNameTypeClass()
    {
        return __NAMESPACE__ . '\\InternetGatewayFilterNameType';
    }
}
