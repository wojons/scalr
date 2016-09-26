<?php
use Scalr\Acl\Acl;

class Scalr_UI_Controller_Dashboard_Widget_Costanalytics extends Scalr_UI_Controller_Dashboard_Widget
{
    public function getDefinition()
    {
        return array(
            'type' => 'local'
        );
    }

    public function getContent($params = array())
    {
        $this->request->restrictAccess(Acl::RESOURCE_ANALYTICS_ENVIRONMENT);
        
        if (!$params['farmCount'])
            $params['farmCount'] = 5;

        $startDate = new DateTime('now', new DateTimeZone('UTC'));
        $allowedEnvs = [$this->environment->id];

        return [
            'farms' => $this->getContainer()->analytics->usage->getTopFarmsPeriodData($this->environment->clientId, $allowedEnvs, 'month', $startDate->format('Y-m-01'), null, $params['farmCount'])
        ];
    }
}