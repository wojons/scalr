<?php
namespace Scalr\Service\CloudStack\Services\Iso\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * ExtractIsoData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ExtractIsoData extends AbstractDataType
{

    /**
     * Required
     * The ID of the ISO file
     *
     * @var string
     */
    public $id;

    /**
     * Required
     * The mode of extraction - HTTP_DOWNLOAD or FTP_UPLOAD
     *
     * @var string
     */
    public $mode;

    /**
     * The url to which the ISO would be extracted
     *
     * @var string
     */
    public $url;

    /**
     * The ID of the zone where the ISO is originally located
     *
     * @var string
     */
    public $zoneid;

    /**
     * Constructor
     *
     * @param   string  $id        The ID of the ISO file
     * @param   string  $mode      The mode of extraction - HTTP_DOWNLOAD or FTP_UPLOAD
     */
    public function __construct($id, $mode)
    {
        $this->id = $id;
        $this->mode = $mode;
    }

}
