<?php

class Scalr_UI_Controller_Account2_Core extends Scalr_UI_Controller
{
    public function settingsAction()
    {
        self::loadController('Core')->settingsAction();
    }
}
