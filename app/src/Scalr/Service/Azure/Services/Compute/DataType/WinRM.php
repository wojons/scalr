<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * WinRM
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\WinListenerList   $listeners Contains configuration settings for the Windows Remote Management service on the Virtual Machine.
 *            This enables remote Windows PowerShell.
 *
 */
class WinRM extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['listeners'];

    /**
     * Constructor
     *
     * @param   array|WinListenerList    $listeners       WinRM listener list
     */
    public function __construct($listeners)
    {
        $this->setListeners($listeners);
    }

    /**
     * Sets WinListenerList
     *
     * @param   array|WinListenerList $listeners Contains configuration settings for the Windows Remote Management service on the Virtual Machine.
     *                                           This enables remote Windows PowerShell.
     * @return  WinRM
     */
    public function setListeners($listeners = null)
    {
        if (!($listeners instanceof WinListenerList)) {
            $listenerList = new WinListenerList();

            foreach ($listeners as $listener) {
                if (!($listener instanceof WinListenerData)) {
                    $listenerData = WinListenerData::initArray($listener);
                } else {
                    $listenerData = $listener;
                }

                $listenerList->append($listenerData);
            }
        } else {
            $listenerList = $listeners;
        }

        return $this->__call(__FUNCTION__, [$listenerList]);
    }

}