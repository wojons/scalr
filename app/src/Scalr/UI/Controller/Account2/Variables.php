<?php

use Scalr\Acl\Acl;
use Scalr\UI\Request\JsonData;
use Scalr\DataType\ScopeInterface;

class Scalr_UI_Controller_Account2_Variables extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_GLOBAL_VARIABLES_ACCOUNT);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $vars = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), 0, ScopeInterface::SCOPE_ACCOUNT);
        $this->response->page('ui/account2/variables/view.js', array('variables' => json_encode($vars->getValues())), array('ui/core/variablefield.js'));
    }

    /**
     * @param JsonData $variables JSON encoded structure
     */
    public function xSaveVariablesAction(JsonData $variables)
    {
        $this->request->restrictAccess(Acl::RESOURCE_GLOBAL_VARIABLES_ACCOUNT, Acl::PERM_GLOBAL_VARIABLES_ACCOUNT_MANAGE);

        $vars = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), 0, ScopeInterface::SCOPE_ACCOUNT);
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
