<?php

use Scalr\Acl\Acl;

class Scalr_UI_Controller_Dashboard_Widget_Monitoring extends Scalr_UI_Controller_Dashboard_Widget
{
    public function getDefinition()
    {
        return array(
            'type' => 'nonlocal'
        );
    }

    /**
     * {@inheritdoc}
     * @see     Scalr_UI_Controller_Dashboard_Widget::hasWidgetAccess
     */
    public function hasWidgetAccess($params)
    {
        if (!empty($params['farmId'])) {
            $farm = DBFarm::LoadByID($params['farmId']);
            $this->request->restrictFarmAccess($farm, Acl::PERM_FARMS_STATISTICS);
        } else {
            throw new Scalr_Exception_Core('Farm ID could not be empty');
        }
    }
}