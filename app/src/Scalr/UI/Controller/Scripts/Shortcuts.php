<?php

use Scalr\Acl\Acl;
use Scalr\Model\Entity\ScriptShortcut;
use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Scripts_Shortcuts extends Scalr_UI_Controller
{

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_ADMINISTRATION_SCRIPTS);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    // TODO: move to higher scope, may be model Farm (static method)
    public function getAllowedFarmId()
    {
        $sql = 'SELECT id FROM farms WHERE env_id = ?';
        $args = [$this->getEnvironmentId()];
        if (! $this->request->isAllowed(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_NOT_OWNED_FARMS)) {
            $sql .= ' AND created_by_id = ?';
            $args[] = $this->user->getId();
        }

        return $this->db->GetCol($sql, $args);
    }

    /**
     * @param JsonData $shortcutId
     */
    public function xRemoveAction(JsonData $shortcutId)
    {
        $errors = [];
        foreach ($shortcutId as $id) {
            try {
                /* @var ScriptShortcut $shortcut */
                $shortcut = ScriptShortcut::findPk($id);
                if (! $shortcut)
                    throw new Scalr_UI_Exception_NotFound();

                if (! in_array($shortcut->farmId, $this->getAllowedFarmId()))
                    throw new Scalr_Exception_InsufficientPermissions();

                $shortcut->delete();

            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (count($errors))
            $this->response->warning("Shortcut(s) successfully removed, but some errors were occurred:\n" . implode("\n", $errors));
        else
            $this->response->success('Shortcut(s) successfully removed');
    }

    public function viewAction()
    {
        $this->response->page('ui/scripts/shortcuts/view.js');
    }

    /**
     * @param JsonData $sort
     * @param int $start
     * @param int $limit
     */
    public function xListAction(JsonData $sort, $start = 0, $limit = 20)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ADMINISTRATION_SCRIPTS);

        $result = ScriptShortcut::find(['farmId' => ['$in' => $this->getAllowedFarmId()]], Scalr\UI\Utils::convertOrder($sort, ['scriptId' => 'ASC'], ['scriptId', 'farmId', 'farmRoleId']), $limit, $start, true);
        $data = [];
        foreach ($result as $shortcut) {
            /* @var ScriptShortcut $shortcut */
            $s = get_object_vars($shortcut);
            $s['farmName'] = DBFarm::LoadByIDOnlyName($shortcut->farmId);
            $s['scriptName'] = $shortcut->getScriptName();
            try {
                $farmRole = DBFarmRole::LoadByID($shortcut->farmRoleId);
                $s['farmRoleName'] = $farmRole->Alias ? $farmRole->Alias : $farmRole->GetRoleObject()->name;
            } catch (Exception $e) {}
            $data[] = $s;
        }

        $this->response->data([
            'total' => $result->totalNumber,
            'data' => $data
        ]);
    }
}
