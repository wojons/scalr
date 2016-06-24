<?php

use Scalr\Service\Azure\Services\Storage\DataType\AccountData;
use Scalr\Model\Entity;

class Scalr_UI_Controller_Tools_Azure_StorageAccounts extends Scalr_UI_Controller
{
    public function createAction()
    {
        $this->response->page('ui/tools/azure/storageaccounts/create.js');
    }

    /**
     * @param string $cloudLocation
     * @param string $resourceGroup
     * @param string $accountType
     * @param string $name
     * @throws Exception
     */
    public function xCreateAction($cloudLocation, $resourceGroup, $accountType, $name)
    {
        $azure = $this->environment->azure();

        if (str_replace(' ', '', $name) != $name) {
            throw new Exception(sprintf("Azure error. Invalid Storage Account's name %s. Spaces are not allowed.", $name), 400);
        }

        $storageAccount = $azure->storage->account->create(
            $this->environment->keychain(SERVER_PLATFORMS::AZURE)->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
            $resourceGroup,
            $name,
            new AccountData($cloudLocation, ['accountType' => $accountType])
        );

        $this->response->data([
            'storageAccount' => [
                'id'    => $name, //azure doesn't return newly created storage account name
                'name'  => $name
            ]
        ]);
    }

}
