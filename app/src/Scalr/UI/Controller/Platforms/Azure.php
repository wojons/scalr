<?php

use Scalr\Modules\Platforms\Azure\AzurePlatformModule;
use Scalr\Modules\PlatformFactory;
use Scalr\Model\Entity;

class Scalr_UI_Controller_Platforms_Azure extends Scalr_UI_Controller
{
    public function xGetResourceGroupsAction()
    {
        $data = array();
        $azure = $this->environment->azure();

        //Get Resource groups;
        $rGroups = $azure->resourceManager->resourceGroup->getList(
            $this->environment
                 ->keychain(SERVER_PLATFORMS::AZURE)
                 ->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID]
        );

        foreach ($rGroups as $rGroup) {
            $data[] = [
                'id' => $rGroup->name,
                'name' => "{$rGroup->name} ({$rGroup->location})"
            ];
        }

        $p = PlatformFactory::NewPlatform(\SERVER_PLATFORMS::AZURE);

        $this->response->data(array('data' => [
            'resourceGroups' => $data,
            'cloudLocations' => $p->getLocations($this->environment)
        ]));
    }

    public function xGetOptionsAction($resourceGroup, $cloudLocation)
    {
        $data = [
            'virtualNetworks' => [],
            'availabilitySets' => [],
            'storageAccounts' => []
        ];
        $azure = $this->environment->azure();

        //Get virtual networks
        $vNets = $azure->network->virtualNetwork->getList(
            $this->environment->keychain(SERVER_PLATFORMS::AZURE)->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
            $resourceGroup
        );
        foreach ($vNets as $vNet) {

            if (strtolower(str_replace(" ", "", $vNet->location)) != $cloudLocation)
                continue;

            $subnets = [];
            foreach ($vNet->properties->subnets as $subnet) {
                $subnets[] = ['id' => $subnet->name, 'name' => "{$subnet->name} ({$subnet->properties->addressPrefix})"];
            }

            $data['virtualNetworks'][] = [
                'id' => $vNet->name,
                'name' => $vNet->name,
                'subnets' => $subnets
            ];
        }

        //Get Availability sets
        $availSets = $azure->compute->availabilitySet->getList(
            $this->environment->keychain(SERVER_PLATFORMS::AZURE)->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
            $resourceGroup
        );

        foreach ($availSets as $availSet) {

            if (strtolower(str_replace(" ", "", $availSet->location)) != $cloudLocation)
                continue;

            $data['availabilitySets'][] = [
                'id' => $availSet->name,
                'name' => $availSet->name
            ];
        }

        $storageAccounts = $azure->storage->account->getList(
            $this->environment->keychain(SERVER_PLATFORMS::AZURE)->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
            $resourceGroup
        );

        foreach ($storageAccounts as $storageAccount) {

            if (strtolower(str_replace(" ", "", $storageAccount->location)) != $cloudLocation)
                continue;

            $data['storageAccounts'][] = [
                'id' => $storageAccount->name,
                'name' => $storageAccount->name
            ];
        }


        $this->response->data(array('data' => $data));
    }
}
