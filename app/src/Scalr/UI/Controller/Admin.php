<?php

class Scalr_UI_Controller_Admin extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return $this->user->isScalrAdmin();
    }
}
