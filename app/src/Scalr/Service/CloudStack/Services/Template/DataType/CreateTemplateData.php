<?php
namespace Scalr\Service\CloudStack\Services\Template\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * CreateTemplateData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class CreateTemplateData extends AbstractDataType
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
     * 32 or 64 bit
     *
     * @var string
     */
    public $bits;

    /**
     * Template details in key/value pairs.
     *
     * @var string
     */
    public $details;

    /**
     * True if template contains XS/VMWare tools inorder to support dynamic scaling of VM cpu/memory
     *
     * @var string
     */
    public $isdynamicallyscalable;

    /**
     * True if this template is a featured template, false otherwise
     *
     * @var string
     */
    public $isfeatured;

    /**
     * True if this template is a public template, false otherwise
     *
     * @var string
     */
    public $ispublic;

    /**
     * True if the template supports the password reset feature; default is false
     *
     * @var string
     */
    public $passwordenabled;

    /**
     * True if the template requres HVM, false otherwise
     *
     * @var string
     */
    public $requireshvm;

    /**
     * The ID of the snapshot the template is being created from.
     * Either this parameter, or volumeId has to be passed in
     *
     * @var string
     */
    public $snapshotid;

    /**
     * The tag for this template.
     *
     * @var string
     */
    public $templatetag;

    /**
     * Optional, only for baremetal hypervisor.
     * The directory name where template stored on CIFS server
     *
     * @var string
     */
    public $url;

    /**
     * Optional, VM ID. If this presents, it is going to create a baremetal template for VM this ID refers to.
     * This is only for VM whose hypervisor type is BareMetal
     *
     * @var string
     */
    public $virtualmachineid;

    /**
     * The ID of the disk volume the template is being created from.
     * Either this parameter, or snapshotId has to be passed in
     *
     * @var string
     */
    public $volumeid;

    /**
     * Constructor
     *
     * @param   string  $displaytext        The display text of the template.
     *                                      This is usually used for display purposes.
     * @param   string  $name               The name of the template
     * @param   string  $ostypeid           The ID of the OS Type that best represents the OS of this template.
     */
    public function __construct($displaytext, $name, $ostypeid)
    {
        $this->displaytext = $displaytext;
        $this->name = $name;
        $this->ostypeid = $ostypeid;
    }

}
