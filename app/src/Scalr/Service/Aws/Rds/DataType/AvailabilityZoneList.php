<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\RdsListDataType;

/**
 * AvailabilityZoneList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     19.01.2015
 */
class AvailabilityZoneList extends RdsListDataType
{

    /**
     * Constructor
     *
     * @param array|AvailabilityZoneData  $aListData List of AvailabilityZoneData objects
     */
    public function __construct($aListData = null)
    {
        parent::__construct(
            $aListData,
            array('name'),
            __NAMESPACE__ . '\\AvailabilityZoneData');
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'AvailabilityZones', $member = true)
    {
        return parent::getQueryArray($uriParameterName, $member);
    }
}