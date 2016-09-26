<?php
namespace Scalr\Service\CloudStack\Services\Instance\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * UpdateVirtualMachineData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class UpdateVirtualMachineData extends AbstractDataType
{

    /**
     * Required
     * The ID of the virtual machine
     *
     * @var string
     */
    public $id;

    /**
     * User generated name
     *
     * @var string
     */
    public $displayname;

    /**
     * An optional field, whether to the display the vm to the end user or not.
     *
     * @var string
     */
    public $displayvm;

    /**
     * Group of the virtual machine
     *
     * @var string
     */
    public $group;

    /**
     * True if high-availability is enabled for the virtual machine, false otherwise
     *
     * @var string
     */
    public $haenable;

    /**
     * True if VM contains XS/VMWare tools inorder to support dynamic scaling of VM cpu/memory
     *
     * @var string
     */
    public $isdynamicallyscalable;

    /**
     * The ID of the OS type that best represents this VM.
     *
     * @var string
     */
    public $ostypeid;

    /**
     * An optional binary data that can be sent to the virtual machine upon a successful deployment.
     * This binary data must be base64 encoded before adding it to the request.
     * Using HTTP GET (via querystring), you can send up to 2KB of data after base64 encoding.
     * Using HTTP POST(via POST body), you can send up to 32K of data after base64 encoding.
     *
     * @var string
     */
    public $userdata;

    /**
     * Constructor
     *
     * @param   string  $id        The ID of the virtual machine
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

}
