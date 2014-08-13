<?php
namespace Scalr\Service\CloudStack\Services\VmGroup\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * CreateInstanceGroupData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class CreateInstanceGroupData extends AbstractDataType
{

    /**
     * Required
     * The name of the instance group
     *
     * @var string
     */
    public $name;

    /**
     * The account of the snapshot.
     * The account parameter must be used with the domainId parameter.
     *
     * @var string
     */
    public $account;

    /**
     * The domain ID of account owning the instance group
     *
     * @var string
     */
    public $domainid;

    /**
     * The project of the instance group
     *
     * @var string
     */
    public $projectid;

    /**
     * Constructor
     *
     * @param   string  $name    The name of the instance group
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

}
