<?php

class Scalr_UI_Controller_Admin_Services_Chef extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return $this->user->isScalrAdmin();
    }

    public function serversAction()
    {
        self::loadController('Servers', 'Scalr_UI_Controller_Services_Chef')->defaultAction();
    }

}
