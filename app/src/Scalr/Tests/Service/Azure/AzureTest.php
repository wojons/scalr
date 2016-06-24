<?php

namespace Scalr\Tests\Service\Azure;

use Scalr\Service\Azure;
use Scalr\Service\Azure\DataType\ProviderData;
use Scalr\Service\Azure\Services\Compute\DataType\CreateAvailabilitySet;
use Scalr\Service\Azure\Services\Compute\DataType\CreateResourceExtension;
use Scalr\Service\Azure\Services\Compute\DataType\CreateVirtualMachine;
use Scalr\Service\Azure\Services\Compute\DataType\OsDisk;
use Scalr\Service\Azure\Services\Compute\DataType\OsProfile;
use Scalr\Service\Azure\Services\Compute\DataType\ResourceExtensionProperties;
use Scalr\Service\Azure\Services\Compute\DataType\StorageProfile;
use Scalr\Service\Azure\Services\Compute\DataType\VirtualMachineProperties;
use Scalr\Service\Azure\Services\Network\DataType\CreateInterface;
use Scalr\Service\Azure\Services\Network\DataType\CreatePublicIpAddress;
use Scalr\Service\Azure\Services\Network\DataType\CreateSecurityGroup;
use Scalr\Service\Azure\Services\Network\DataType\CreateSecurityRule;
use Scalr\Service\Azure\Services\Network\DataType\CreateVirtualNetwork;
use Scalr\Service\Azure\Services\Network\DataType\InterfaceIpConfigurationsData;
use Scalr\Service\Azure\Services\Network\DataType\InterfaceProperties;
use Scalr\Service\Azure\Services\Network\DataType\IpConfigurationProperties;
use Scalr\Service\Azure\Services\Network\DataType\SecurityGroupProperties;
use Scalr\Service\Azure\Services\Network\DataType\SecurityRuleData;
use Scalr\Service\Azure\Services\Network\DataType\VirtualNetworkProperties;
use Scalr\Service\Azure\Services\Storage\DataType\AccountData;
use Scalr\Tests\TestCase;
use Exception;
use SERVER_PLATFORMS;
use Scalr\Model\Entity;

/**
 * AzureTest
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
class AzureTest extends TestCase
{

    const TEST_TYPE = TestCase::TEST_TYPE_CLOUD_DEPENDENT;

    /**
     * @var Azure
     */
    private $azure;

    /**
     * @var string
     */
    private $subscriptionId;

    /**
     * @var string
     */
    private $resourceGroupName;

    /**
     * @var string
     */
    private $availabilitySetName;

    /**
     * @var \Scalr_Environment
     */
    private $testEnv;

    /**
     * @var string
     */
    private $publicIpName;

    /**
     * @var string
     */
    private $nicName;

    /**
     * @var string
     */
    private $vnName;

    /**
     * @var string
     */
    private $storageName;

    /**
     * @var string
     */
    private $vmName;

    /**
     * @var string
     */
    private $sgName;

    /**
     * Set test names for objects
     */
    protected function setUp()
    {
        parent::setUp();

        if (static::isSkippedFunctionalTest()) {
            $this->markTestSkipped();
        }

        $testEnvId = \Scalr::config('scalr.phpunit.envid');

        try {
            $this->testEnv = \Scalr_Environment::init()->loadById($testEnvId);
        } catch (Exception $e) {
            $this->markTestSkipped('Test Environment does not exist.');
        }

        if (!$this->testEnv || !$this->testEnv->isPlatformEnabled(\SERVER_PLATFORMS::AZURE)) {
            $this->markTestSkipped('Azure platform is not enabled.');
        }

        $this->azure = $this->testEnv->azure();

        $this->subscriptionId = $this->azure->getEnvironment()->keychain(SERVER_PLATFORMS::AZURE)->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID];

        $this->resourceGroupName = 'test3-resource-group-' . $this->getInstallationId();
        $this->availabilitySetName = 'test3-availability-set-' . $this->getInstallationId();
        $this->vmName = 'test3-virtual-machine-' . $this->getInstallationId();
        $this->vnName = 'test3-virtual-network-' . $this->getInstallationId();
        $this->nicName = 'test3-network-interface' . $this->getInstallationId();
        $this->publicIpName = 'myPublicIP3';
        $this->storageName = 'teststorage3' . $this->getInstallationId();
        $this->sgName = 'test3-security-group' . $this->getInstallationId();
    }

    /**
     * @test
     * @functional
     */
    public function testAzureFunctional()
    {
        $region = 'westus';
        $containerName = 'vhds';
        $osDiskName = 'osdisk1';

        $providers = $this->azure->getProvidersList($this->subscriptionId);

        $this->assertNotEmpty($providers);

        foreach ($providers as $provider) {
            /* @var $provider ProviderData */
            $this->assertNotEmpty($provider->namespace);
            $this->assertNotEmpty($provider->registrationState);
        }

        $provider = $this->azure->getLocationsList(ProviderData::RESOURCE_PROVIDER_COMPUTE);
        $this->assertNotEmpty($provider->namespace);

        $this->deleteAllTestObjects();

        $createResGroup = $this->azure->resourceManager->resourceGroup->create($this->subscriptionId, $this->resourceGroupName, $region);
        $this->assertNotEmpty($createResGroup);

        $request = new CreateAvailabilitySet($this->availabilitySetName, $region);
        $request->tags = ['key' => 'value'];
        $request->setProperties([
            'platformUpdateDomainCount' => 5,
            'platformFaultDomainCount'  => 3
        ]);

        $createAs = $this->azure->compute->availabilitySet->create(
            $this->subscriptionId, $this->resourceGroupName, $request
        );

        $this->assertNotEmpty($createAs);

        $virtualNetworkProperties = new VirtualNetworkProperties([
            ['name' => 'mysubnet1', 'properties' => ["addressPrefix" => "10.1.0.0/24"]]
        ]);
        $virtualNetworkProperties->addressSpace = ["addressPrefixes" => ["10.1.0.0/16", "10.2.0.0/16"]];
        $createVirtualNetwork = new CreateVirtualNetwork($this->vnName, $region, $virtualNetworkProperties);

        $vnResponse = $this->azure->network->virtualNetwork->create($this->subscriptionId, $this->resourceGroupName, $createVirtualNetwork);

        $this->assertNotEmpty($vnResponse);

        $createPublicIpAddress = new CreatePublicIpAddress($region, ["publicIPAllocationMethod" => 'Dynamic', 'settings' => ['domainNameLabel' => 'scalrtestlabel67']]);

        $nicIpAddressResponse = $this->azure->network->publicIPAddress->create($this->subscriptionId, $this->resourceGroupName, $this->publicIpName, $createPublicIpAddress);
        $this->assertNotEmpty($nicIpAddressResponse);

        $ipConfigProperties = new IpConfigurationProperties(
            ["id" => "/subscriptions/" . $this->subscriptionId . "/resourceGroups/" . $this->resourceGroupName . "/providers/Microsoft.Network/virtualNetworks/" . $this->vnName . "/subnets/mysubnet1"], "Dynamic"
        );

        $ipConfigProperties->publicIPAddress = ["id" => "/subscriptions/" . $this->subscriptionId . "/resourceGroups/" . $this->resourceGroupName . "/providers/Microsoft.Network/publicIPAddresses/" . $this->publicIpName];

        $nicProperties = new InterfaceProperties(
            [new InterfaceIpConfigurationsData('ipconfig1', $ipConfigProperties)]
        );

        $createSecurityRule = new CreateSecurityRule('Tcp', '23-45', '46-56', '*', '*', 'Allow', 123, 'Inbound');
        $ruleData = new SecurityRuleData();
        $ruleData->name = 'rule_name';
        $ruleData->setProperties($createSecurityRule);

        $securityGroupProperties = new SecurityGroupProperties();
        $securityGroupProperties->setSecurityRules([$ruleData]);

        $createSecurityGroup = new CreateSecurityGroup($region);
        $createSecurityGroup->setProperties($securityGroupProperties);

        $sgResponse = $this->azure->network->securityGroup->create($this->subscriptionId, $this->resourceGroupName, $this->sgName, $createSecurityGroup);
        $this->assertNotEmpty($sgResponse);

        $secGroupId = "/subscriptions/" . $this->subscriptionId . "/resourceGroups/" . $this->resourceGroupName . "/providers/Microsoft.Network/networkSecurityGroups/" . $this->sgName;
        $nicProperties->setNetworkSecurityGroup(['id' => $secGroupId]);

        $createNic = new CreateInterface($region, $nicProperties);

        $nicResponse = $this->azure->network->interface->create($this->subscriptionId, $this->resourceGroupName, $this->nicName, $createNic);
        $this->assertNotEmpty($nicResponse);

        $nicInfo = $this->azure->network->interface->getInfo($this->subscriptionId, $this->resourceGroupName, $this->nicName);
        $this->assertNotEmpty($nicInfo);

        $disassociate = $this->azure->network->publicIPAddress->disassociate($this->subscriptionId, $this->resourceGroupName, $this->nicName, $this->publicIpName);
        $this->assertTrue($disassociate);
        $associate = $this->azure->network->publicIPAddress->associate($this->subscriptionId, $this->resourceGroupName, $this->nicName, $this->publicIpName);
        $this->assertTrue($associate);

        $createStorage = new AccountData($region, ['accountType' => 'Standard_LRS']);
        $storageResponse = $this->azure->storage->account->create($this->subscriptionId, $this->resourceGroupName, $this->storageName, $createStorage);
        $this->assertNotEmpty($storageResponse);

        $networkProfile = [
            "networkInterfaces" => [
                ["id" => "/subscriptions/" . $this->subscriptionId
                . "/resourceGroups/" . $this->resourceGroupName
                . "/providers/Microsoft.Network/networkInterfaces/" . $this->nicName
            ]]
        ];

        $osProfile = new OsProfile('vladtest34', 'Tex6-HBU*7');
        $osProfile->computerName = 'testscalr';

        $vhd = ['uri' => 'https://' . $this->storageName . '.blob.core.windows.net/' . $containerName . '/' . $osDiskName . '.vhd'];

        $storageProfile = new StorageProfile(new OsDisk($osDiskName, $vhd, 'FromImage'));

        $publishers = $this->azure->compute->location->getPublishersList($this->subscriptionId, $region);
        $this->assertNotEmpty($publishers);

        $offers = null;

        foreach ($publishers as $publisher) {
            $this->assertObjectHasAttribute('name', $publisher);
            $offers = $this->azure->compute->location->getOffersList($this->subscriptionId, $region, $publisher->name);
            
            if (!empty($offers)) {
                break;
            }
        }

        $this->assertNotNull($offers);

        $offer = reset($offers);
        $this->assertObjectHasAttribute('name', $offer);

        $skus = $this->azure->compute->location->getSkusList($this->subscriptionId, $region, $publisher->name, $offer->name);
        $this->assertNotEmpty($skus);

        $sku = reset($skus);
        $this->assertObjectHasAttribute('name', $sku);

        $versions = $this->azure->compute->location->getVersionsList($this->subscriptionId, $region, $publisher->name, $offer->name, $sku->name);
        $this->assertNotEmpty($versions);

        $version = reset($versions);
        $this->assertObjectHasAttribute('name', $version);

        $storageProfile->setImageReference([
            'publisher' => 'Canonical',
            'offer'     => 'UbuntuServer',
            'sku'       => '14.04.2-LTS',
            'version'   => 'latest'
        ]);

        $vmProperties = new VirtualMachineProperties(
            ["vmSize" => "Standard_A0"],
            $networkProfile,
            $storageProfile,
            $osProfile
        );

        //$this->azure->getClient()->setDebug();
        $vmRequest = new CreateVirtualMachine($this->vmName, $region, $vmProperties);
        $createVm = $this->azure->compute->virtualMachine->create($this->subscriptionId, $this->resourceGroupName, $vmRequest);
        $this->assertNotEmpty($createVm);

        $modelInfo = $this->azure->compute->virtualMachine->getModelViewInfo($this->subscriptionId, $this->resourceGroupName, $this->vmName, true);
        $this->assertNotEmpty($modelInfo);

        $extensionProperties = new ResourceExtensionProperties('Microsoft.OSTCExtensions', 'CustomScriptForLinux', '1.2');
        // for Windows use "new ResourceExtensionProperties('Microsoft.Compute', 'CustomScriptExtension', '1.0')"
        $extensionProperties->setSettings([
            'fileUris' => ["http://tru4.scalr.com/public/installScalarizr?osType=linux&repo=latest&platform=azure"],
            'commandToExecute' => 'bash -c "cat installScalarizr | bash && service scalr-upd-client start"']);

        $createExtension = new CreateResourceExtension('scalarizr', $region, $extensionProperties);
        $this->azure->compute->resourceExtension->create($this->subscriptionId, $this->resourceGroupName, $this->vmName, $createExtension);
        $this->deleteAllTestObjects();
    }

    /**
     * Clean up from test data
     */
    private function deleteAllTestObjects()
    {
        try {
            $infoAvail = $this->azure->compute->availabilitySet->getInfo($this->subscriptionId, $this->resourceGroupName, $this->availabilitySetName);
            $this->assertNotEmpty($infoAvail);

            $delete = $this->azure->compute->availabilitySet->delete($this->subscriptionId, $this->resourceGroupName, $this->availabilitySetName);
            $this->assertTrue($delete);
        } catch (Exception $e) {}

        try {
            $infoRes = $this->azure->resourceManager->resourceGroup->getInfo($this->subscriptionId, $this->resourceGroupName);
            $this->assertNotEmpty($infoRes);

            $delete = $this->azure->resourceManager->resourceGroup->delete($this->subscriptionId, $this->resourceGroupName);
            $this->assertTrue($delete);
        } catch (Exception $e) {}

        try {
            $this->azure->network->publicIPAddress->getInfo($this->subscriptionId, $this->resourceGroupName, $this->publicIpName);

            $delete = $this->azure->network->publicIPAddress->delete($this->subscriptionId, $this->resourceGroupName, $this->publicIpName);
            $this->assertTrue($delete);
        } catch (Exception $e) {}

        try {
            $this->azure->network->securityGroup->getInfo($this->subscriptionId, $this->resourceGroupName, $this->sgName);

            $delete = $this->azure->network->securityGroup->delete($this->subscriptionId, $this->resourceGroupName, $this->sgName);
            $this->assertTrue($delete);
        } catch (Exception $e) {}

        try {
            $this->azure->network->interface->getInfo($this->subscriptionId, $this->resourceGroupName, $this->nicName);

            $delete = $this->azure->network->interface->delete($this->subscriptionId, $this->resourceGroupName, $this->nicName);
            $this->assertTrue($delete);
        } catch (Exception $e) {}

        try {
            $this->azure->network->virtualNetwork->getInfo($this->subscriptionId, $this->resourceGroupName, $this->vnName);

            $delete = $this->azure->network->virtualNetwork->delete($this->subscriptionId, $this->resourceGroupName, $this->vnName);
            $this->assertTrue($delete);
        } catch (Exception $e) {}

        try {
            $this->azure->storage->account->getProperties($this->subscriptionId, $this->resourceGroupName, $this->storageName);

            $delete = $this->azure->storage->account->delete($this->subscriptionId, $this->resourceGroupName, $this->storageName);
            $this->assertTrue($delete);
        } catch (Exception $e) {}

        try {
            $this->azure->compute->virtualMachine->getInstanceViewInfo($this->subscriptionId, $this->resourceGroupName, $this->vmName);

            $delete = $this->azure->compute->virtualMachine->delete($this->subscriptionId, $this->resourceGroupName, $this->vmName);
            $this->assertTrue($delete);
        } catch (Exception $e) {}
    }

    /**
     * Tears down the fixture - unset Azure instance.
     *
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        unset($this->azure, $this->testEnv);
        parent::tearDown();
    }

}