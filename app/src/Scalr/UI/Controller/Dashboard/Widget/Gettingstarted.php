<?php

use Scalr\Model\Entity\Account\User;

class Scalr_UI_Controller_Dashboard_Widget_Gettingstarted extends Scalr_UI_Controller_Dashboard_Widget
{
    public function getDefinition()
    {
        return [
            'type' => 'local'
        ];
    }

    public function getContent($params = [])
    {
        if ($this->user->getType() != User::TYPE_SCALR_ADMIN) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        return [];
    }
}
