<?php
namespace Scalr\Service\Aws\Route53\DataType;

use Scalr\Service\Aws\Route53\AbstractRoute53DataType;

/**
 * ZoneServerData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class ZoneServerData extends AbstractRoute53DataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = array();

    /**
     * The identifier for the hosted zone
     *
     * @var string
     */
    public $nameServer;

}