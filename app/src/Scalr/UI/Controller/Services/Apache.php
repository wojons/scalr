<?php
use Scalr\Acl\Acl;

class Scalr_UI_Controller_Services_Apache extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_SERVICES_APACHE);
    }

}
