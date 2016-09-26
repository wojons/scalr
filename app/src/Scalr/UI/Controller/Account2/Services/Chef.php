<?php

class Scalr_UI_Controller_Account2_Services_Chef extends Scalr_UI_Controller
{
    public function serversAction()
    {
        self::loadController('Servers', 'Scalr_UI_Controller_Services_Chef')->defaultAction();
    }

}
