<?php

use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Account2_Variables extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(\Scalr\Acl\Acl::RESOURCE_ADMINISTRATION_GLOBAL_VARIABLES);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $vars = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), 0, Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT);
        $this->response->page('ui/account2/variables/view.js', array('variables' => json_encode($vars->getValues())), array('ui/core/variablefield.js'), array('ui/core/variablefield.css'));
    }

    /**
     * @param JsonData $variables JSON encoded structure
     */
    public function xSaveVariablesAction(JsonData $variables)
    {
        $vars = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), 0, Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT);
        $result = $vars->setValues($variables, 0, 0, 0, '', false);
        if ($result === true)
            $this->response->success('Variables saved');
        else {
            $this->response->failure();
            $this->response->data(array(
                'errors' => array(
                    'variables' => $result
                )
            ));
        }
    }
}
