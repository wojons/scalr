<?php

use Scalr\Service\Azure\Services\Network\DataType\CreateVirtualNetwork;
use Scalr\Service\Azure\Services\Network\DataType\VirtualNetworkProperties;
use Scalr\Model\Entity;
use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Tools_Azure_VirtualNetworks extends Scalr_UI_Controller
{

    public function createAction()
    {
        $this->response->page('ui/tools/azure/virtualnetworks/create.js');
    }

    /**
     * @param string $cloudLocation
     * @param string $resourceGroup
     * @param string $name
     * @param string $addressPrefix
     * @param JsonData $subnets
     * @throws Exception
     */
    public function xCreateAction($cloudLocation, $resourceGroup, $name, $addressPrefix, JsonData $subnets = null)
    {
        $azure = $this->environment->azure();

        if (str_replace(' ', '', $name) != $name) {
            throw new Exception(sprintf("Azure error. Invalid Virtual Network's name %s. Spaces are not allowed.", $name), 400);
        }

        $vnSubnets = [];

        if ($subnets) {
            foreach ($subnets as $subnet) {
                $vnSubnets[] = [
                    'name' => $subnet->name,
                    'properties' => ['addressPrefix' => $subnet['addressPrefix']]
                ];
            }
        }

        $vnProperties = new VirtualNetworkProperties($vnSubnets);
        $vnProperties->addressSpace = ["addressPrefixes" => [$addressPrefix]];

        $vn = $azure->network->virtualNetwork->create(
            $this->environment->keychain(SERVER_PLATFORMS::AZURE)->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
            $resourceGroup,
            new CreateVirtualNetwork($name, $cloudLocation, $vnProperties)
        );

        $vnSubnets = [];

        foreach ($vn->properties->subnets as $subnet) {
            $vnSubnets[] = ['id' => $subnet->name, 'name' => "{$subnet->name} ({$subnet->properties->addressPrefix})"];
        }

        $this->response->data([
            'virtualNetwork' => [
                'id'      => $vn->name,
                'name'    => $vn->name,
                'subnets' => $vnSubnets
            ]
        ]);
    }

    public function createSubnetAction()
    {
        $this->response->page('ui/tools/azure/virtualnetworks/createSubnet.js');
    }

    /**
     * @param string   $resourceGroup
     * @param string   $virtualNetwork
     * @param string   $name
     * @param string   $addressPrefix
     */
    public function xCreateSubnetAction($resourceGroup, $virtualNetwork,  $name, $addressPrefix)
    {
        $azure = $this->environment->azure();

        $vn = $azure->network
                    ->virtualNetwork
                    ->getInfo(
                        $this->environment
                             ->keychain(SERVER_PLATFORMS::AZURE)
                             ->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
                        $resourceGroup,
                        $virtualNetwork
                    );
        foreach ($vn->properties->subnets as $subnet) {
            if ($name == $subnet->name) {
                $this->response->failure(sprintf(_("Subnet \"%s\" already exists"), $name));
                return;
            }
        }
        $subnet = $azure->network
                        ->subnet
                        ->create(
                            $this->environment
                                 ->keychain(SERVER_PLATFORMS::AZURE)
                                 ->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
                            $resourceGroup,
                            $virtualNetwork,
                            $name,
                            $addressPrefix
                        );

        $this->response->data([
            'debug' => $subnet,
            'subnet' => [
                'id' => $name,
                'name' => "{$name} ({$addressPrefix})"
            ]
        ]);
    }

}
