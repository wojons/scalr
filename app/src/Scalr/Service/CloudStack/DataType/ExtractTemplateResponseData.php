<?php
namespace Scalr\Service\CloudStack\DataType;

use DateTime;

/**
 * ExtractTemplateResponseData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ExtractTemplateResponseData extends AbstractDataType
{

    /**
     * The id of extracted object
     *
     * @var string
     */
    public $id;

    /**
     * The account id to which the extracted object belongs
     *
     * @var string
     */
    public $accountid;

    /**
     * The time and date the object was created
     *
     * @var DateTime
     */
    public $created;

    /**
     * The upload id of extracted object
     *
     * @var string
     */
    public $extractId;

    /**
     * The mode of extraction - upload or download
     *
     * @var string
     */
    public $extractMode;

    /**
     * The name of the extracted object
     *
     * @var string
     */
    public $name;

    /**
     * The state of the extracted object
     *
     * @var string
     */
    public $state;

    /**
     * The status of the extraction
     *
     * @var string
     */
    public $status;

    /**
     * Type of the storage
     *
     * @var string
     */
    public $storagetype;

    /**
     * The percentage of the entity uploaded to the specified location
     *
     * @var string
     */
    public $uploadpercentage;

    /**
     * If mode = upload then url of the uploaded entity.
     * If mode = download the url from which the entity can be downloaded
     *
     * @var string
     */
    public $url;

    /**
     * Zone ID the object was extracted from
     *
     * @var string
     */
    public $zoneid;

    /**
     * Zone name the object was extracted from
     *
     * @var string
     */
    public $zonename;

}