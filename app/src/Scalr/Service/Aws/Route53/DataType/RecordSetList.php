<?php
namespace Scalr\Service\Aws\Route53\DataType;

use Scalr\Service\Aws\Route53\AbstractRoute53ListDataType;

/**
 * RecordSetList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 * @property  string $marker
 * @property  int    $maxItems
 * @property  bool   $isTruncated
 */
class RecordSetList extends AbstractRoute53ListDataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = array();

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('marker', 'maxItems', 'isTruncated');

    /**
     * Constructor
     *
     * @param array|RecordSetData  $aListData  RecordSetData List
     */
    public function __construct ($aListData = null)
    {
        parent::__construct(
            $aListData,
            'name',
            'Scalr\\Service\\Aws\\Route53\\DataType\\RecordSetData'
        );
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'name', $member = true)
    {
        return parent::getQueryArray($uriParameterName);
    }
}