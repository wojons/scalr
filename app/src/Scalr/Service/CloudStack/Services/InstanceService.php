<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\VirtualMachineInstancesData;
use Scalr\Service\CloudStack\DataType\VirtualMachineInstancesList;
use Scalr\Service\CloudStack\Services\Instance\DataType\DeployVirtualMachineData;
use Scalr\Service\CloudStack\Services\Instance\DataType\ListVirtualMachinesData;
use Scalr\Service\CloudStack\Services\Instance\DataType\UpdateVirtualMachineData;
use Scalr\Service\CloudStack\Services\Instance\DataType\VMPasswordData;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 *
 * @method   \Scalr\Service\CloudStack\Services\Instance\V26032014\InstanceApi getApiHandler()
 *           getApiHandler()
 *           Gets an Instance API handler for the specific version
 */
class InstanceService extends AbstractService implements ServiceInterface
{

    const VERSION_26032014 = 'V26032014';

    const VERSION_DEFAULT = self::VERSION_26032014;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Services.ServiceInterface::getType()
     */
    public static function getType()
    {
        return CloudStack::SERVICE_INSTANCE;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Services.ServiceInterface::getVersion()
     */
    public function getVersion()
    {
        return self::VERSION_DEFAULT;
    }

    /**
     * Creates and automatically starts a virtual machine based on a service offering, disk offering, and template.
     *
     * @param DeployVirtualMachineData|array $request Request data for deploying VM.
     * @return VirtualMachineInstancesData
     */
    public function deploy($request)
    {
        if ($request !== null && !($request instanceof DeployVirtualMachineData)) {
            $request = DeployVirtualMachineData::initArray($request);
        }
        return $this->getApiHandler()->deployVirtualMachine($request);
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
    public function destroy($id, $expunge = null)
    {
        return $this->getApiHandler()->destroyVirtualMachine($id, $expunge);
    }
    
    /**
     * Expunges a virtual machine. Once destroyed, only the administrator can recover it.
     *
     * @param string $id      The ID of the virtual machine
     * @return VirtualMachineInstancesData
     */
    public function expunge($id)
    {
        return $this->getApiHandler()->expungeVirtualMachine($id);
    }
    

    /**
     * Reboots a virtual machine.
     *
     * @param string $id The ID of the virtual machine
     * @return VirtualMachineInstancesData
     */
    public function reboot($id)
    {
        return $this->getApiHandler()->rebootVirtualMachine($id);
    }

    /**
     * Starts a virtual machine.
     *
     * @param string $id The ID of the virtual machine
     * @return VirtualMachineInstancesData
     */
    public function start($id)
    {
        return $this->getApiHandler()->startVirtualMachine($id);
    }

    /**
     * Stops a virtual machine.
     *
     * @param string $id The ID of the virtual machine
     * @param string $forced Force stop the VM.  The caller knows the VM is stopped.
     * @return VirtualMachineInstancesData
     */
    public function stop($id, $forced = null)
    {
        return $this->getApiHandler()->stopVirtualMachine($id, $forced);
    }

    /**
     * Resets the password for virtual machine.
     * The virtual machine must be in a "Stopped" state and the template must already support this feature for this command to take effect. [async]
     *
     * @param string $id The ID of the virtual machine
     * @return VirtualMachineInstancesData
     */
    public function resetPassword($id)
    {
        return $this->getApiHandler()->resetPasswordForVirtualMachine($id);
    }

    /**
     * Changes the service offering for a virtual machine. The virtual machine must be in a "Stopped" state for this command to take effect.
     *
     * @param string $id The ID of the virtual machine
     * @param string $serviceOfferingId the service offering ID to apply to the virtual machine
     * @return VirtualMachineInstancesData
     */
    public function changeService($id, $serviceOfferingId)
    {
        return $this->getApiHandler()->changeServiceForVirtualMachine($id, $serviceOfferingId);
    }

    /**
     * Updates parameters of a virtual machine.
     *
     * @param UpdateVirtualMachineData|array $request Update virtual machine request data
     * @return VirtualMachineInstancesData
     */
    public function update($request)
    {
        if ($request !== null && !($request instanceof UpdateVirtualMachineData)) {
            $request = UpdateVirtualMachineData::initArray($request);
        }
        return $this->getApiHandler()->updateVirtualMachine($request);
    }

    /**
     * List the virtual machines owned by the account.
     *
     * @param ListVirtualMachinesData|array $filter    List virtual machines request data
     * @param PaginationType                $pagination     Pagination data
     * @return VirtualMachineInstancesList|null
     */
    public function describe($filter = null, PaginationType $pagination = null)
    {
        if ($filter !== null && !($filter instanceof ListVirtualMachinesData)) {
            $filter = ListVirtualMachinesData::initArray($filter);
        }
        return $this->getApiHandler()->listVirtualMachines($filter, $pagination);
    }

    /**
     * Returns an encrypted password for the VM
     *
     * @param string $id The ID of the virtual machine
     * @return VMPasswordData
     */
    public function getPassword($id)
    {
        return $this->getApiHandler()->getVMPassword($id);
    }

}