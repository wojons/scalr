<?php
namespace Scalr\Service\Aws;

use Scalr\Service\Aws\Ec2\DataType\AccountAttributeSetList;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\DataType\ListDataType;
use Scalr\Service\Aws\Ec2\DataType\RegionInfoList;
use Scalr\Service\Aws;

/**
 * Amazon EC2 interface
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     26.12.2012
 *
 * @property  \Scalr\Service\Aws\Ec2\Handler\AvailabilityZoneHandler $availabilityZone Gets an AvailabilityZone service interface handler.
 * @property  \Scalr\Service\Aws\Ec2\Handler\SecurityGroupHandler    $securityGroup    Gets a SecurityGroup service interface handler.
 * @property  \Scalr\Service\Aws\Ec2\Handler\InstanceHandler         $instance         Gets an Instance service interface handler.
 * @property  \Scalr\Service\Aws\Ec2\Handler\ReservedInstanceHandler $reservedInstance Gets a ReservedInstance service interface handler.
 * @property  \Scalr\Service\Aws\Ec2\Handler\VolumeHandler           $volume           Gets a Volume service interface handler.
 * @property  \Scalr\Service\Aws\Ec2\Handler\TagHandler              $tag              Gets a Tag service interface handler.
 * @property  \Scalr\Service\Aws\Ec2\Handler\ImageHandler            $image            Gets an Image service interface handler.
 * @property  \Scalr\Service\Aws\Ec2\Handler\KeyPairHandler          $keyPair          Gets an KeyPair service interface handler.
 * @property  \Scalr\Service\Aws\Ec2\Handler\AddressHandler          $address          Gets an Elastic IP Addresses service interface handler.
 * @property  \Scalr\Service\Aws\Ec2\Handler\SnapshotHandler         $snapshot         Gets an Snapshot service interface handler.
 * @property  \Scalr\Service\Aws\Ec2\Handler\SubnetHandler           $subnet           Gets an Subnet service interface handler.
 * @property  \Scalr\Service\Aws\Ec2\Handler\PlacementGroupHandler   $placementGroup   Gets an PlacementGroup service interface handler.
 * @property  \Scalr\Service\Aws\Ec2\Handler\VpcHandler              $vpc              Gets an Vpc service interface handler.
 * @property  \Scalr\Service\Aws\Ec2\Handler\NetworkInterfaceHandler $networkInterface Gets an NetworkInterface service interface handler.
 * @property  \Scalr\Service\Aws\Ec2\Handler\InternetGatewayHandler  $internetGateway  Gets an InternetGateway service interface handler.
 * @property  \Scalr\Service\Aws\Ec2\Handler\RouteTableHandler       $routeTable       Gets an RouteTable service interface handler.
 *
 * @method    \Scalr\Service\Aws\Ec2\V20150415\Ec2Api getApiHandler() getApiHandler()  Gets an Ec2Api handler
 */
class Ec2 extends AbstractService implements ServiceInterface
{

    /**
     * API Version 20140615
     */
    const API_VERSION_20140615 = '20140615';

    /**
     * API Version 20150415
     */
    const API_VERSION_20150415 = '20150415';

    /**
     * Current version of the API
     */
    const API_VERSION_CURRENT = self::API_VERSION_20150415;

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\AbstractService::getName()
     */
    public function getName()
    {
        return 'ec2';
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.ServiceInterface::getAllowedEntities()
     */
    public function getAllowedEntities()
    {
        return array(
            'availabilityZone', 'securityGroup', 'instance',
            'reservedInstance', 'volume', 'tag', 'image',
            'keyPair', 'address', 'snapshot', 'subnet', 'placementGroup',
            'vpc', 'networkInterface', 'internetGateway', 'routeTable',
        );
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.ServiceInterface::getAvailableApiVersions()
     */
    public function getAvailableApiVersions()
    {
        return array(
            self::API_VERSION_20140615,
            self::API_VERSION_20150415,
        );
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.ServiceInterface::getCurrentApiVersion()
     */
    public function getCurrentApiVersion()
    {
        return self::API_VERSION_CURRENT;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.ServiceInterface::getUrl()
     */
    public function getUrl()
    {
        //overrides region by default
        $args = func_get_args();
        if (isset($args[0])) {
            $region = $args[0];
        } else {
            $aws = $this->getAws();
            $region = $aws->getRegion();
        }

        if (strpos($region, 'cn-') === 0) {
            return 'ec2.' . $region . '.amazonaws.com.cn';
        } else {
            return 'ec2' . (empty($region) ? '' : '.' . $region) . '.amazonaws.com';
        }
    }

    /**
     * DescribeAccountAttributes
     *
     * Describes the specified attribute of your AWS account.
     *
     * @param   ListDataType|array|string  $attributeNameList List of the The following table lists the supported account attributes
     * @return  AccountAttributeSetList     Returns list of the names and values of the requested attributes
     * @throws  ClientException
     * @throws  Ec2Exception
     */
    public function describeAccountAttributes($attributeNameList)
    {
        if (!($attributeNameList instanceof ListDataType)) {
            $attributeNameList = new ListDataType($attributeNameList);
        }
        return $this->getApiHandler()->describeAccountAttributes($attributeNameList);
    }

    /**
     * DescribeRegions action
     *
     * @return  RegionInfoList  Returns the list of the RegionInfoData objects on success
     * @throws  ClientException
     * @throws  Ec2Exception
     */
    public function describeRegions()
    {
        return $this->getApiHandler()->describeRegions();
    }
}
