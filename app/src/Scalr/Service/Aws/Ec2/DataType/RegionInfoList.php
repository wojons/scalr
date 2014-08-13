<?php
namespace Scalr\Service\Aws\Ec2\DataType;

use Scalr\Service\Aws\Ec2\Ec2ListDataType;

/**
 * RegionInfoList
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.0 (28.01.2014)
 */
class RegionInfoList extends Ec2ListDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('requestId');

    /**
     * Constructor
     *
     * @param array|RegionInfoData  $aListData List of RegionInfoData objects
     */
    public function __construct($aListData = null)
    {
        parent::__construct($aListData, array('regionName', 'regionEndpoint'), __NAMESPACE__ . '\\RegionInfoData');
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'Regions', $member = true)
    {
        return parent::getQueryArray($uriParameterName, $member);
    }
}