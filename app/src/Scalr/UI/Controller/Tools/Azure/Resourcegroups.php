<?php

use Scalr\Model\Entity;

class Scalr_UI_Controller_Tools_Azure_ResourceGroups extends Scalr_UI_Controller
{
    
    public function createAction()
    {
        $this->response->page('ui/tools/azure/resourcegroups/create.js');
    }

    /**
     * @param string $cloudLocation
     * @param string $name
     * @throws Exception
     */
    public function xCreateAction($cloudLocation, $name)
    {
        $azure = $this->environment->azure();

        if (str_replace(' ', '', $name) != $name) {
            throw new Exception(sprintf("Azure error. Invalid Resource Group's name %s. Spaces are not allowed.", $name), 400);
        }

        $rGroup = $azure->resourceManager->resourceGroup->create(
            $this->environment->keychain(SERVER_PLATFORMS::AZURE)->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
            $name,
            $cloudLocation
        );

        $this->response->data([
            'resourceGroup' => [
                'id'   => $rGroup->name,
                'name' => "{$rGroup->name} ({$rGroup->location})"
            ]
        ]);
    }

}
