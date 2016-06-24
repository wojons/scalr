<?php

class Scalr_UI_Controller_Admin_Roles extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'roleId';

    public function hasAccess()
    {
        return $this->user->isAdmin();
    }

    public function defaultAction()
    {
        Scalr_UI_Controller_Roles::controller()->callActionMethod('defaultAction');
    }

    public function editAction()
    {
        Scalr_UI_Controller_Roles::controller()->callActionMethod('editAction');
    }
    
    public function categoriesAction()
    {
        self::loadController('Categories', 'Scalr_UI_Controller_Roles')->defaultAction();
    }
    
}
