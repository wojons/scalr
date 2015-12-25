<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;
use Scalr\Service\Azure\Services\Network\DataType\InterfaceData;
use Scalr\Service\Azure\Services\Network\DataType\InterfaceList;

/**
 * NetworkProfile
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\InterfaceList   $networkInterfaces
 *
 */
class NetworkProfile extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['networkInterfaces'];

    /**
     * Sets InterfaceList
     *
     * @param   array|InterfaceList  $networkInterfaces
     * @return  NetworkProfile
     */
    public function setNetworkInterfaces($networkInterfaces = null)
    {
        if (!($networkInterfaces instanceof InterfaceList)) {
            $networkInterfaceList = new InterfaceList();

            foreach ($networkInterfaces as $networkInterface) {
                if (!($networkInterface instanceof InterfaceData)) {
                    $networkInterfaceData = InterfaceData::initArray($networkInterface);
                } else {
                    $networkInterfaceData = $networkInterface;
                }

                $networkInterfaceList->append($networkInterfaceData);
            }
        } else {
            $networkInterfaceList = $networkInterfaces;
        }

        return $this->__call(__FUNCTION__, [$networkInterfaceList]);
    }

}