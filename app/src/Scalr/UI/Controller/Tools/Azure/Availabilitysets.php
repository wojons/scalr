<?php

use Scalr\Service\Azure\Services\Compute\DataType\CreateAvailabilitySet;
use Scalr\Model\Entity;

class Scalr_UI_Controller_Tools_Azure_AvailabilitySets extends Scalr_UI_Controller
{
    
    public function createAction()
    {
        $this->response->page('ui/tools/azure/availabilitysets/create.js');
    }

    /**
     * @param string $cloudLocation
     * @param string $resourceGroup
     * @param string $name
     * @throws Exception
     */
    public function xCreateAction($cloudLocation, $resourceGroup, $name)
    {
        $azure = $this->environment->azure();

        if (str_replace(' ', '', $name) != $name) {
            throw new Exception(sprintf("Azure error. Invalid Availability Set's name %s. Spaces are not allowed.", $name), 400);
        }

        $availSet = $azure->compute->availabilitySet->create(
            $this->environment->keychain(SERVER_PLATFORMS::AZURE)->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
            $resourceGroup,
            new CreateAvailabilitySet($name, $cloudLocation)
        );

        $this->response->data([
            'availabilitySet' => [
                'id'    => $availSet->name,
                'name'  => $availSet->name
            ]
        ]);
    }

}
