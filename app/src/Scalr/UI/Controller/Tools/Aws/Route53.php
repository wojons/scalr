<?php

use Scalr\Acl\Acl;

class Scalr_UI_Controller_Tools_Aws_Route53 extends Scalr_UI_Controller
{
   public function hasAccess()
   {
       if (!parent::hasAccess() || !$this->request->isAllowed(Acl::RESOURCE_AWS_ROUTE53)) return false;

       $enabledPlatforms = $this->getEnvironment()->getEnabledPlatforms();
       if (!in_array(SERVER_PLATFORMS::EC2, $enabledPlatforms))
           throw new Exception("You need to enable EC2 platform for current environment");

       return true;
   }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $locations = self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false);
        $regions = array_keys($locations);
        $this->response->page('ui/tools/aws/route53/view.js', array(
            'regions'   => $regions
        ));
    }
}