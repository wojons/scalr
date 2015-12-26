<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * VmAgentData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\StatusList  $statuses
 *
 */
class VmAgentData extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['statuses'];

    /**
     * Vm agent version
     *
     * @var string
     */
    public $vmAgentVersion;

    /**
     * Sets statuses
     *
     * @param   array|StatusList $statuses
     * @return  DiskData
     */
    public function setStatuses($statuses = null)
    {
        if (!($statuses instanceof StatusList)) {
            $statusList = new StatusList();

            foreach ($statuses as $status) {
                if (!($status instanceof StatusData)) {
                    $statusData = StatusData::initArray($status);
                } else {
                    $statusData = $status;
                }

                $statusList->append($statusData);
            }
        } else {
            $statusList = $statuses;
        }

        return $this->__call(__FUNCTION__, [$statusList]);
    }

}