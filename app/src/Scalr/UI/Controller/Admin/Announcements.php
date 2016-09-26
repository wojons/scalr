<?php

class Scalr_UI_Controller_Admin_Announcements extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return $this->user->isScalrAdmin();
    }

    public function defaultAction()
    {
        Scalr_UI_Controller_Announcements::controller()->callActionMethod('defaultAction');
    }
}