<?php
class Scalr_UI_Controller_Admin_Webhooks extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return $this->user->isScalrAdmin();
    }

    public function endpointsAction()
    {
        self::loadController('Endpoints', 'Scalr_UI_Controller_Webhooks')->defaultAction();
    }

    public function configsAction()
    {
        self::loadController('Configs', 'Scalr_UI_Controller_Webhooks')->defaultAction();
    }

    public function historyAction()
    {
        self::loadController('History', 'Scalr_UI_Controller_Webhooks')->defaultAction();
    }
}
