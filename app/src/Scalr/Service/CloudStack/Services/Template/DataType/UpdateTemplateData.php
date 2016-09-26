<?php
namespace Scalr\Service\CloudStack\Services\Template\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * UpdateTemplateData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class UpdateTemplateData extends AbstractDataType
{

    /**
     * Required
     * The ID of the image file
     *
     * @var string
     */
    public $id;

    /**
     * True if image is bootable, false otherwise
     *
     * @var string
     */
    public $bootable;

    /**
     * The display text of the image
     *
     * @var string
     */
    public $displaytext;

    /**
     * The format for the image
     *
     * @var string
     */
    public $format;

    /**
     * True if template/ISO contains XS/VMWare tools inorder to support dynamic scaling of VM cpu/memory
     *
     * @var string
     */
    public $isdynamicallyscalable;

    /**
     * True if the template type is routing i.e.,
     * if template is used to deploy router
     *
     * @var string
     */
    public $isrouting;

    /**
     * The name of the image file
     *
     * @var string
     */
    public $name;

    /**
     * The ID of the OS type that best represents the OS of this image.
     *
     * @var string
     */
    public $ostypeid;

    /**
     * True if the image supports the password reset feature; default is false
     *
     * @var string
     */
    public $passwordenabled;

    /**
     * Sort key of the template, integer
     *
     * @var string
     */
    public $sortkey;

    /**
     * Constructor
     *
     * @param   string  $id     The ID of the image file
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

}
