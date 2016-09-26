<?php

namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\RdsListDataType;

/**
 * DBClusterSnapshot List
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     07.10.2015
 *
 * @property string    $marker
 *           An optional pagination token provided by a previous request.
 *           If this parameter is specified, the response includes only
 *           records beyond the marker, up to the value specified by MaxRecords
 *
 * @method   string         getMarker() getMarker()     Gets a Marker.
 * @method   DBInstanceList setMarker() setMarker($val) Sets a Marker value.
 */
class DBClusterSnapshotList extends RdsListDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['marker'];

    /**
     * Constructor
     *
     * @param array|DBClusterSnapshotData  $aListData List of DBClusterSnapshotData objects
     */
    public function __construct($aListData = null)
    {
        parent::__construct($aListData, ['dBClusterSnapshotIdentifier'], __NAMESPACE__ . '\\DBClusterSnapshotData');
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType\ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'DBClusterSnapshots', $member = true)
    {
        return parent::getQueryArray($uriParameterName, $member);
    }

}