<?php
namespace Scalr\Service\CloudStack\Services\Volume\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * ExtractVolumeData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ExtractVolumeData extends AbstractDataType
{

    /**
     * Required
     * The ID of the volume
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
     * Required
     * The ID of the zone where the ISO is originally located
     *
     * @var string
     */
    public $zoneid;

    /**
     * Constructor
     *
     * @param   string  $id        The ID of the volume
     * @param   string  $mode      The mode of extraction - HTTP_DOWNLOAD or FTP_UPLOAD
     * @param   string  $zoneId    The ID of the zone where the volume is located
     */
    public function __construct($id, $mode, $zoneId)
    {
        $this->id = $id;
        $this->mode = $mode;
        $this->zoneid = $zoneId;
    }

}
