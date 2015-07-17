<?php

class Scalr_UI_Controller_Analytics_Projects extends Scalr_UI_Controller
{
    /*
     * Redirects to account analytics controller
     */
    public function addAction($projectId = null)
    {
        self::loadController('Projects', 'Scalr_UI_Controller_Account2_Analytics')->addAction($projectId);
    }

    public function editAction($projectId = null)
    {
        self::loadController('Projects', 'Scalr_UI_Controller_Account2_Analytics')->editAction($projectId);
    }
}
