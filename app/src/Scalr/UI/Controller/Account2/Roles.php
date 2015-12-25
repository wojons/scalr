<?php

class Scalr_UI_Controller_Account2_Roles extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'roleId';

    public function defaultAction()
    {
        Scalr_UI_Controller_Roles::controller()->callActionMethod('defaultAction');
    }

    public function editAction()
    {
        Scalr_UI_Controller_Roles::controller()->callActionMethod('editAction');
    }
}
