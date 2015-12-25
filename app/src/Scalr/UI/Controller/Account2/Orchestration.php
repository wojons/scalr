<?php
use Scalr\Acl\Acl;
use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Account2_Orchestration extends Scalr_UI_Controller
{
    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_ORCHESTRATION_ACCOUNT);

        $this->response->page('ui/account2/orchestration/view.js', array(
            'orchestrationRules' => $this->getOrchestrationRules(),
            'scriptData' => \Scalr\Model\Entity\Script::getScriptingData($this->user->getAccountId(), null)
        ), array( 'ui/scripts/scriptfield.js', 'ui/services/chef/chefsettings.js'), array( 'ui/scripts/scriptfield.css'));
    }

    /**
     * @param JsonData $orchestrationRules JSON encoded structure
     */
    public function xSaveAction(JsonData $orchestrationRules)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ORCHESTRATION_ACCOUNT, Acl::PERM_ORCHESTRATION_ACCOUNT_MANAGE);

        $ids = array();
        foreach ($orchestrationRules as $rule) {
            // TODO: check permission for script_id
            if (!$rule['rule_id']) {
                $this->db->Execute('INSERT INTO account_scripts SET
                    `account_id` = ?,
                    `event_name` = ?,
                    `target` = ?,
                    `script_id` = ?,
                    `version` = ?,
                    `timeout` = ?,
                    `issync` = ?,
                    `params` = ?,
                    `order_index` = ?,
                    `script_path` = ?,
                    `run_as` = ?,
                    `script_type` = ?
                ', array(
                    $this->user->getAccountId(),
                    $rule['event_name'],
                    $rule['target'],
                    $rule['script_id'] != 0 ? $rule['script_id'] : NULL,
                    $rule['version'],
                    $rule['timeout'],
                    $rule['isSync'],
                    serialize($rule['params']),
                    $rule['order_index'],
                    $rule['script_path'],
                    $rule['run_as'],
                    $rule['script_type']
                ));
                $ids[] = $this->db->Insert_ID();
            } else {
                $this->db->Execute('UPDATE account_scripts SET
                    `event_name` = ?,
                    `target` = ?,
                    `script_id` = ?,
                    `version` = ?,
                    `timeout` = ?,
                    `issync` = ?,
                    `params` = ?,
                    `order_index` = ?,
                    `script_path` = ?,
                    `run_as` = ?,
                    `script_type` = ?
                    WHERE id = ? AND account_id = ?
                ', array(
                    $rule['event_name'],
                    $rule['target'],
                    $rule['script_id'] != 0 ? $rule['script_id'] : NULL,
                    $rule['version'],
                    $rule['timeout'],
                    $rule['isSync'],
                    serialize($rule['params']),
                    $rule['order_index'],
                    $rule['script_path'],
                    $rule['run_as'],
                    $rule['script_type'],

                    $rule['rule_id'],
                    $this->user->getAccountId()
                ));
                $ids[] = $rule['rule_id'];
            }
        }

        $toRemove = $this->db->Execute('SELECT id FROM account_scripts WHERE account_id = ? AND id NOT IN (\'' . implode("','", $ids) . '\')', array($this->user->getAccountId()));
        while ($rScript = $toRemove->FetchRow()) {
            $this->db->Execute("DELETE FROM account_scripts WHERE id = ?", array($rScript['id']));
        }

        $this->response->success('Orchestration saved');
    }

    public function xGetListAction()
    {
        $this->response->data(['data' => $this->getOrchestrationRules()]);
    }

    public function getOrchestrationRules()
    {
        $rules = [];

        $dbRules = $this->db->Execute("
            SELECT account_scripts.*, scripts.name AS script_name, scripts.os
            FROM account_scripts
            LEFT JOIN scripts ON account_scripts.script_id = scripts.id
            WHERE account_scripts.account_id = ?", array($this->user->getAccountId()));

        while ($rule = $dbRules->FetchRow()) {
            $rules[] = array(
                'rule_id' => (int) $rule['id'],
                'event_name' => $rule['event_name'],
                'target' => $rule['target'],
                'script_id' => (int) $rule['script_id'],
                'script_name' => $rule['script_name'],
                'os' => $rule['os'],
                'version' => (int) $rule['version'],
                'timeout' => $rule['timeout'],
                'isSync' => (int) $rule['issync'],
                'params' => unserialize($rule['params']),
                'order_index' => $rule['order_index'],
                'script_path' => $rule['script_path'],
                'run_as' => $rule['run_as'],
                'script_type' => $rule['script_type']
            );
        }
        return $rules;
    }
}
