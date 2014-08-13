<?php

use Scalr\Acl\Acl;

class Scalr_UI_Controller_Tools_Openstack_Lb extends Scalr_UI_Controller
{
    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_OPENSTACK_ELB);
    }
}
