<?php
namespace Scalr\Service\CloudStack\Services\Template\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * RegisterTemplateData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class RegisterTemplateData extends AbstractDataType
{

    /**
     * Required
     * The display text of the template. This is usually used for display purposes.
     *
     * @var string
     */
    public $displaytext;

    /**
     * Required
     * The format for the template.
     * Possible values include QCOW2, RAW, and VHD.
     *
     * @var string
     */
    public $format;

    /**
     * Required
     * The target hypervisor for the template
     *
     * @var string
     */
    public $hypervisor;

    /**
     * Required
     * The name of the template
     *
     * @var string
     */
    public $name;

    /**
     * Required
     * The ID of the OS Type that best represents the OS of this template.
     *
     * @var string
     */
    public $ostypeid;

    /**
     * Required
     * The URL of where the template is hosted.
     * Possible URL include http:// and https://
     *
     * @var string
     */
    public $url;

    /**
     * Required
     * The ID of the zone the template is to be hosted on
     *
     * @var string
     */
    public $zoneid;

    /**
     * An optional accountName. Must be used with domainId.
     *
     * @var string
     */
    public $account;

    /**
     * 32 or 64 bits support. 64 by default
     *
     * @var string
     */
    public $bits;

    /**
     * The MD5 checksum value of this template
     *
     * @var string
     */
    public $checksum;

    /**
     * Template details in key/value pairs.
     *
     * @var string
     */
    public $details;

    /**
     * An optional domainId. If the account parameter is used, domainId must also be used.
     *
     * @var string
     */
    public $domainid;

    /**
     * True if template contains XS/VMWare tools inorder to support dynamic scaling of VM cpu/memory
     *
     * @var string
     */
    public $isdynamicallyscalable;

    /**
     * True if the template or its derivatives are extractable; default is false
     *
     * @var string
     */
    public $isextractable;

    /**
     * True if this template is a featured template, false otherwise
     *
     * @var string
     */
    public $isfeatured;

    /**
     * True if this template is a public template, false otherwise;  default is true
     *
     * @var string
     */
    public $ispublic;

    /**
     * True if the template type is routing i.e., if template is used to deploy router
     *
     * @var string
     */
    public $isrouting;

    /**
     * True if the template supports the password reset feature; default is false
     *
     * @var string
     */
    public $passwordenabled;

    /**
     * Register template for the project
     *
     * @var string
     */
    public $projectid;

    /**
     * True if this template requires HVM
     *
     * @var string
     */
    public $requireshvm;

    /**
     * True if the template supports the sshkey upload feature; default is false
     *
     * @var string
     */
    public $sshkeyenabled;

    /**
     * The tag for this template.
     *
     * @var string
     */
    public $templatetag;

    /**
     * Constructor
     *
     * @param   string  $displaytext        The display text of the template.
     *                                      This is usually used for display purposes.
     * @param   string  $format             The format for the template. Possible values include QCOW2, RAW, and VHD.
     * @param   string  $hypervisor         The target hypervisor for the template
     * @param   string  $name               The name of the template
     * @param   string  $ostypeid           The ID of the OS Type that best represents the OS of this template.
     * @param   string  $url                The URL of where the template is hosted. Possible URL include http:// and https://
     * @param   string  $zoneid             The ID of the zone the template is to be hosted on
     */
    public function __construct($displaytext, $format, $hypervisor, $name, $ostypeid, $url, $zoneid)
    {
        $this->displaytext = $displaytext;
        $this->format = $format;
        $this->hypervisor = $hypervisor;
        $this->name = $name;
        $this->ostypeid = $ostypeid;
        $this->url = $url;
        $this->zoneid = $zoneid;
    }

}
