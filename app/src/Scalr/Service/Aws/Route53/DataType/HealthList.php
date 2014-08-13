<?php
namespace Scalr\Service\Aws\Route53\DataType;

use Scalr\Service\Aws\Route53\AbstractRoute53ListDataType;

/**
 * HealthList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 * @property  string $marker
 * @property  int    $maxItems
 * @property  bool   $isTruncated
 */
class HealthList extends AbstractRoute53ListDataType
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
     * @param array|HealthData  $aListData  HealthData List
     */
    public function __construct ($aListData = null)
    {
        parent::__construct(
            $aListData,
            'healthId',
            'Scalr\\Service\\Aws\\Route53\\DataType\\HealthData'
        );
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'healthId', $member = true)
    {
        return parent::getQueryArray($uriParameterName);
    }
}