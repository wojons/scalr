<?php

class Scalr_UI_Controller_Tools_Azure extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        $enabledPlatforms = $this->getEnvironment()->getEnabledPlatforms();
        if (!in_array(SERVER_PLATFORMS::AZURE, $enabledPlatforms))
            throw new Exception('You need to enable Azure platform for current environment');

        return parent::hasAccess();
    }

}
