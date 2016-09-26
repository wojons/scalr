<?php
namespace Scalr\Service\Aws\Route53\DataType;

use Scalr\Service\Aws\Route53\AbstractRoute53DataType;
use DateTime;
/**
 * ZoneChangeInfoData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class ZoneChangeInfoData extends AbstractRoute53DataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = array();

    /**
     * Unique identifier for the change batch request
     *
     * @var string
     */
    public $id;

    /**
     * PENDING | INSYNC
     *
     * @var string
     */
    public $status;

    /**
     * Date and time in Coordinated Universal Time format
     *
     * @var DateTime
     */
    public $submittedAt;

}