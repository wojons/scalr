<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\RdsListDataType;

/**
 * CharacterSetList
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
class CharacterSetList extends RdsListDataType
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
     * @param array|CharacterSetData  $aListData List of CharacterSetData objects
     */
    public function __construct($aListData = null)
    {
        parent::__construct($aListData, array('characterSetName'), __NAMESPACE__ . '\\CharacterSetData');
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'SupportedCharacterSets', $member = true)
    {
        return parent::getQueryArray($uriParameterName, $member);
    }
}