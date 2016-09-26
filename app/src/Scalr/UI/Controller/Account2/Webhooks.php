<?php
use Scalr\Acl\Acl;
use Scalr\Model\Entity\WebhookConfig;

class Scalr_UI_Controller_Account2_Webhooks extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return $this->request->getScope() == WebhookConfig::SCOPE_ACCOUNT && $this->request->isAllowed(Acl::RESOURCE_WEBHOOKS_ACCOUNT) && !$this->user->isScalrAdmin();
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
