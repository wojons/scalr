<?php
namespace Scalr\Service\CloudStack\Services\Instance\V26032014;

use Scalr\Service\CloudStack\Client\ClientInterface;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\VirtualMachineInstancesData;
use Scalr\Service\CloudStack\DataType\VirtualMachineInstancesList;
use Scalr\Service\CloudStack\Services\AbstractApi;
use Scalr\Service\CloudStack\Services\TagsTrait;
use Scalr\Service\CloudStack\Services\Instance\DataType\DeployVirtualMachineData;
use Scalr\Service\CloudStack\Services\Instance\DataType\ListVirtualMachinesData;
use Scalr\Service\CloudStack\Services\Instance\DataType\UpdateVirtualMachineData;
use Scalr\Service\CloudStack\Services\Instance\DataType\VMPasswordData;
use Scalr\Service\CloudStack\Services\InstanceService;
use Scalr\Service\CloudStack\Services\VirtualTrait;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class InstanceApi extends AbstractApi
{
    use VirtualTrait, TagsTrait;

    /**
     * @var InstanceService
     */
    protected $service;

    /**
     * Constructor
     *
     * @param   InstanceService $virtual
     */
    public function __construct(InstanceService $virtual)
    {
        $this->service = $virtual;
    }

    /**
     * Gets HTTP Client
     *
     * @return  ClientInterface Returns HTTP Client
     */
    public function getClient()
    {
        return $this->service->getCloudStack()->getClient();
    }

    /**
     * Creates and automatically starts a virtual machine based on a service offering, disk offering, and template.
     *
     * @param DeployVirtualMachineData $requestData Request data for deploying VM.
     * @return VirtualMachineInstancesData
     */
    public function deployVirtualMachine(DeployVirtualMachineData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call(
            'deployVirtualMachine', $requestData->toArray()
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadVirtualMachineInstanceData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Destroys a virtual machine. Once destroyed, only the administrator can recover it.
     *
     * @param string $id      The ID of the virtual machine
     * @param string $expunge If true is passed, the vm is expunged immediately.
     *                        False by default.
     *                        Parameter can be passed to the call by ROOT/Domain admin only
     * @return VirtualMachineInstancesData
     */
    public function destroyVirtualMachine($id, $expunge = null)
    {
        $result = null;

        $response = $this->getClient()->call('destroyVirtualMachine', array(
            'id'      => $this->escape($id),
            'expunge' => $this->escape($expunge)
        ));

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadVirtualMachineInstanceData($resultObject);
            }
        }

        return $result;

    }

    /**
     * Reboots a virtual machine.
     *
     * @param string $id The ID of the virtual machine
     * @return VirtualMachineInstancesData
     */
    public function rebootVirtualMachine($id)
    {
        $result = null;

        $response = $this->getClient()->call('rebootVirtualMachine', array(
            'id' => $this->escape($id),
        ));

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadVirtualMachineInstanceData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Starts a virtual machine.
     *
     * @param string $id The ID of the virtual machine
     * @return VirtualMachineInstancesData
     */
    public function startVirtualMachine($id)
    {
        $result = null;

        $response = $this->getClient()->call('startVirtualMachine', array(
            'id' => $this->escape($id),
        ));

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadVirtualMachineInstanceData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Stops a virtual machine.
     *
     * @param string $id The ID of the virtual machine
     * @param string $forced Force stop the VM.  The caller knows the VM is stopped.
     * @return VirtualMachineInstancesData
     */
    public function stopVirtualMachine($id, $forced = null)
    {
        $result = null;

        $response = $this->getClient()->call('stopVirtualMachine', array(
            'id'      => $this->escape($id),
            'forced'  => $this->escape($forced)
        ));

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadVirtualMachineInstanceData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Resets the password for virtual machine.
     * The virtual machine must be in a "Stopped" state and the template must already support this feature for this command to take effect. [async]
     *
     * @param string $id The ID of the virtual machine
     * @return VirtualMachineInstancesData
     */
    public function resetPasswordForVirtualMachine($id)
    {
        $result = null;

        $response = $this->getClient()->call('resetPasswordForVirtualMachine', array(
            'id' => $this->escape($id),
        ));

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadVirtualMachineInstanceData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Changes the service offering for a virtual machine. The virtual machine must be in a "Stopped" state for this command to take effect.
     *
     * @param string $id The ID of the virtual machine
     * @param string $serviceOfferingId the service offering ID to apply to the virtual machine
     * @return VirtualMachineInstancesData
     */
    public function changeServiceForVirtualMachine($id, $serviceOfferingId)
    {
        $result = null;

        $response = $this->getClient()->call('changeServiceForVirtualMachine', array(
            'id'                => $this->escape($id),
            'serviceofferingid' => $this->escape($serviceOfferingId)
        ));

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadVirtualMachineInstanceData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Updates parameters of a virtual machine.
     *
     * @param UpdateVirtualMachineData $requestData Update virtual machine request data
     * @return VirtualMachineInstancesData
     */
    public function updateVirtualMachine(UpdateVirtualMachineData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call(
            'updateVirtualMachine', $requestData->toArray()
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadVirtualMachineInstanceData($resultObject);
            }
        }

        return $result;
    }

    /**
     * List the virtual machines owned by the account.
     *
     * @param ListVirtualMachinesData $requestData    List virtual machines request data
     * @param PaginationType          $pagination     Pagination data
     * @return VirtualMachineInstancesList|null
     */
    public function listVirtualMachines(ListVirtualMachinesData $requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listVirtualMachines', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadVirtualMachineInstancesList($resultObject->virtualmachine);
            }
        }

        return $result;
    }

    /**
     * Returns an encrypted password for the VM
     *
     * @param string $id The ID of the virtual machine
     * @return VMPasswordData
     */
    public function getVMPassword($id)
    {
        $result = null;

        $response = $this->getClient()->call(
            'getVMPassword', array(
                'id' => $this->escape($id)
            )
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadVMPasswordData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Loads VMPasswordData from json object
     *
     * @param   object $resultObject
     * @return  VMPasswordData Returns VMPasswordData
     */
    protected function _loadVMPasswordData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'encryptedpassword')) {
            $item = new VMPasswordData();
            $item->encryptedpassword = (string) $resultObject->encryptedpassword;
        }

        return $item;
    }

}