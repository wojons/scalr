<?php
class Scalr_UI_Controller_Admin_Images extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return $this->user->isScalrAdmin();
    }

    public function defaultAction()
    {
        self::loadController('Images')->defaultAction();
    }

    public function createAction()
    {
        self::loadController('Images')->createAction();
    }
}
