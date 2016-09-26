<?php
namespace Scalr\Service\Aws\Ec2\DataType;

use Scalr\Service\Aws\Ec2\AbstractEc2DataType;
use DateTime;

/**
 * GetPasswordDataResponseData
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    17.07.2013
 */
class GetPasswordDataResponseData extends AbstractEc2DataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('requestId');

    /**
     * The ID of the instance.
     *
     * @var string
     */
    public $instanceId;

    /**
     * The time the data was last updated
     *
     * @var DateTime
     */
    public $timestamp;

    /**
     * The password of the instance
     *
     * @var string
     */
    public $passwordData;
}