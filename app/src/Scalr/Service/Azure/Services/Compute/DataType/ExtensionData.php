<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * ExtensionData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\StatusList  $statuses
 *
 */
class ExtensionData extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['statuses'];

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $typeHandlerVersion;

    /**
     * Sets statuses
     *
     * @param   array|StatusList $statuses
     * @return  ExtensionData
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