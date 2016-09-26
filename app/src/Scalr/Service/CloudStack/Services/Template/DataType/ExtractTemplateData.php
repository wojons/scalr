<?php
namespace Scalr\Service\CloudStack\Services\Template\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * ExtractTemplateData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ExtractTemplateData extends AbstractDataType
{

    /**
     * Required
     * The ID of the template
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

}