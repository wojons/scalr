<?php
use Scalr\Acl\Acl;

class Scalr_UI_Controller_Dashboard_Widget_Environments extends Scalr_UI_Controller_Dashboard_Widget
{
    public function getDefinition()
    {
        return array(
            'type' => 'local'
        );
    }

    public function getContent($params = array())
    {
        $environments = $this->user->getEnvironments();
        foreach ($environments as &$env) {
            $environment = Scalr_Environment::init()->loadById($env['id']);
            $env['farmsCount'] = $environment->getFarmsCount();
            $env['serversCount'] = $environment->getRunningServersCount();
        }
        return [
            'environments' => $environments
        ];
    }
}