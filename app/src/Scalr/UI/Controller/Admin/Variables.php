<?php

use Scalr\UI\Request\JsonData;
use Scalr\DataType\ScopeInterface;

class Scalr_UI_Controller_Admin_Variables extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return $this->user->isScalrAdmin();
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $vars = new Scalr_Scripting_GlobalVariables(0, 0, ScopeInterface::SCOPE_SCALR);
        $this->response->page('ui/admin/variables/view.js', array('variables' => json_encode($vars->getValues())), array('ui/core/variablefield.js'));
    }

    /**
     * @param JsonData $variables
     */
    public function xSaveVariablesAction(JsonData $variables)
    {
        $vars = new Scalr_Scripting_GlobalVariables(0, 0, ScopeInterface::SCOPE_SCALR);
        $result = $vars->setValues($variables, 0, 0, 0, '', false);
        if ($result === true) {
            $this->response->success('Variables saved');
        } else {
            $this->response->failure();
            $this->response->data(array(
                'errors' => array(
                    'variables' => $result
                )
            ));
        }
    }
}
