<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * JobStatusData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class JobStatusData extends AbstractDataType
{

    /**
     * The ID of the latest async job acting on this object
     *
     * @var string
     */
    public $jobid;

    /**
     * The current status of the latest async job acting on this object
     *
     * @var string
     */
    public $jobstatus;

}