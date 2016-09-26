<?php

class Scalr_UI_Controller_Account2_Images extends Scalr_UI_Controller
{
    public function defaultAction()
    {
        Scalr_UI_Controller_Images::controller()->callActionMethod('defaultAction');
    }

    public function registerAction()
    {
        Scalr_UI_Controller_Images::controller()->callActionMethod('registerAction');
    }
}
