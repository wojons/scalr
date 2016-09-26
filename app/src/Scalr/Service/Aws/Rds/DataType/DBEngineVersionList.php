<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\RdsListDataType;

/**
 * DBEngineVersionList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     12.01.2015
 *
 * @property string    $marker
 *           An optional pagination token provided by a previous request.
 *           If this parameter is specified, the response includes only
 *           records beyond the marker, up to the value specified by MaxRecords
 *
 * @method   string         getMarker() getMarger()     Gets a Marker.
 * @method   DBInstanceList setMarker() setMarker($val) Sets a Marker value.
 */
class DBEngineVersionList extends RdsListDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('marker');

    /**
     * Constructor
     *
     * @param array|DBEngineVersionData  $aListData List of DBEngineVersionData objects
     */
    public function __construct($aListData = null)
    {
        parent::__construct($aListData, array('engineVersion'), __NAMESPACE__ . '\\DBEngineVersionData');
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'DBEngineVersions', $member = true)
    {
        return parent::getQueryArray($uriParameterName, $member);
    }
}