<?php
namespace Scalr\Service\CloudStack\DataType;

use \DateTime;

/**
 * JobResultData
 *
 * @property  \Scalr\Service\CloudStack\DataType\VirtualMachineInstancesData  $virtualmachine
 * The job result reason
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class JobResultData extends JobStatusData
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('virtualmachine');

    /**
     * The account that executed the async command
     *
     * @var string
     */
    public $accountid;

    /**
     * The async command executed
     *
     * @var string
     */
    public $cmd;

    /**
     * The created date of the job
     *
     * @var DateTime
     */
    public $created;

    /**
     * The unique ID of the instance/entity object related to the job
     *
     * @var string
     */
    public $jobinstanceid;

    /**
     * The instance/entity object related to the job
     *
     * @var string
     */
    public $jobinstancetype;

    /**
     * The progress information of the PENDING job
     *
     * @var string
     */
    public $jobprocstatus;

    /**
     * The result code for the job
     *
     * @var string
     */
    public $jobresultcode;

    /**
     * The result type
     *
     * @var string
     */
    public $jobresulttype;

    /**
     * The user that executed the async command
     *
     * @var string
     */
    public $userid;

    /**
     * Error code
     *
     * @var string
     */
    public $errorcode;

    /**
     * Error message
     *
     * @var string
     */
    public $errortext;

    /**
     * Sets virtualmachine
     *
     * @param   VirtualMachineInstancesData $virtualmachine
     * @return  JobResultData
     */
    public function setVirtualmachine(VirtualMachineInstancesData $virtualmachine = null)
    {
        return $this->__call(__FUNCTION__, array($virtualmachine));
    }

}