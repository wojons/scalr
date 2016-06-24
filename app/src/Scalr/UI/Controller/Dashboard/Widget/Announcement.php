<?php

class Scalr_UI_Controller_Dashboard_Widget_Announcement extends Scalr_UI_Controller_Dashboard_Widget
{
    public function getDefinition()
    {
        return ['type' => 'local'];
    }

    public function getContent($params = null)
    {
        // the contents of the widget is updated by individual updater
        return null;
    }
}
