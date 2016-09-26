<?php
namespace Scalr\Service\Aws\Route53\DataType;

use Scalr\Service\Aws\Route53\AbstractRoute53ListDataType;

/**
 * ZoneDelegationSetList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class ZoneDelegationSetList extends AbstractRoute53ListDataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = array();

    /**
     * Constructor
     *
     * @param array|ZoneData  $aListData  ServerData List
     */
    public function __construct ($aListData = null)
    {
        parent::__construct(
            $aListData,
            'nameServer',
            __NAMESPACE__ . '\\ZoneServerData'
        );
    }

}