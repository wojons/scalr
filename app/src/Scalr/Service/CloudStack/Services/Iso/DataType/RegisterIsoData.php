<?php
namespace Scalr\Service\CloudStack\Services\Iso\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * RegisterIsoData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class RegisterIsoData extends AbstractDataType
{

    /**
     * Required
     * The display text of the ISO. This is usually used for display purposes.
     *
     * @var string
     */
    public $displaytext;

    /**
     * Required
     * The name of the ISO
     *
     * @var string
     */
    public $name;

    /**
     * Required
     * The URL to where the ISO is currently being hosted
     *
     * @var string
     */
    public $url;

    /**
     * Required
     * The ID of the zone you wish to register the ISO to.
     *
     * @var string
     */
    public $zoneid;

    /**
     * An optional account name. Must be used with domainId.
     *
     * @var string
     */
    public $account;

    /**
     * True if this ISO is bootable. If not passed explicitly its assumed to be true
     *
     * @var string
     */
    public $bootable;

    /**
     * The MD5 checksum value of this ISO
     *
     * @var string
     */
    public $checksum;

    /**
     * An optional domainId. If the account parameter is used, domainId must also be used.
     *
     * @var string
     */
    public $domainid;

    /**
     * Image store uuid
     *
     * @var string
     */
    public $imagestoreuuid;

    /**
     * True if iso contains XS/VMWare tools inorder to support dynamic scaling of VM cpu/memory
     *
     * @var string
     */
    public $isdynamicallyscalable;

    /**
     * True if the iso or its derivatives are extractable; default is false
     *
     * @var string
     */
    public $isextractable;

    /**
     * True if you want this ISO to be featured
     *
     * @var string
     */
    public $isfeatured;

    /**
     * True if you want to register the ISO to be publicly available to all users, false otherwise.
     *
     * @var string
     */
    public $ispublic;

    /**
     * The ID of the OS Type that best represents the OS of this ISO.
     * If the iso is bootable this parameter needs to be passed
     *
     * @var string
     */
    public $ostypeid;

    /**
     * Register iso for the project
     *
     * @var string
     */
    public $projectid;

    /**
     * Constructor
     *
     * @param   string  $displayText        The display text of the ISO. This is usually used for display purposes.
     * @param   string  $name               The name of the ISO
     * @param   string  $url                The URL to where the ISO is currently being hosted
     * @param   string  $zoneId             The ID of the zone you wish to register the ISO to.
     */
    public function __construct($displayText, $name, $url, $zoneId)
    {
        $this->displaytext = $displayText;
        $this->name = $name;
        $this->url = $url;
        $this->zoneid = $zoneId;
    }

}
