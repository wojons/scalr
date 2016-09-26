<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\RdsListDataType;

/**
 * OrderableDBInstanceOptionsList
 *
 * @property string    $marker
 *           An optional pagination token provided by a previous request.
 *           If this parameter is specified, the response includes only
 *           records beyond the marker, up to the value specified by MaxRecords
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     19.01.2015
 */
class OrderableDBInstanceOptionsList extends RdsListDataType
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
     * @param array|OrderableDBInstanceOptionsData  $aListData List of OrderableDBInstanceOptionsData objects
     */
    public function __construct($aListData = null)
    {
        parent::__construct(
            $aListData,
            array('dBInstanceClass'),
            __NAMESPACE__ . '\\OrderableDBInstanceOptionsData');
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'OrderableDBInstanceOptions', $member = true)
    {
        return parent::getQueryArray($uriParameterName, $member);
    }
}