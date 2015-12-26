<?php

class Scalr_UI_Controller_Admin extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return $this->user->isAdmin();
    }

    public function dashboardAction()
    {
        self::loadController('Dashboard')->defaultAction();
    }

    public function scriptsAction()
    {
        self::loadController('Scripts')->defaultAction();
    }

    public function eventsAction()
    {
        self::loadController('Events', 'Scalr_UI_Controller_Scripts')->defaultAction();
    }
}
