<?php
class Scalr_UI_Controller_Dashboard_Widget extends Scalr_UI_Controller
{
    public function getContent($params = array())
    {
        return array();
    }

    public function getContentAction()
    {
        $this->response->data(array(
            'widgetContent' => $this->getContent()
        ));
    }

    public function xGetContentAction()
    {
        $this->response->data($this->getContent());
    }

    /**
     * Check if user has access to widget based on params
     *
     * @param   array   $params     Params for widget
     * @throws  Scalr_Exception_Core
     * @throws  Scalr_Exception_InsufficientPermissions
     */
    public function hasWidgetAccess($params)
    {

    }
}
