<?php

use Scalr\UI\Request\JsonData;
use Scalr\Model\Entity\RoleCategory;
use Scalr\DataType\ScopeInterface;

class Scalr_UI_Controller_Roles_Categories extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'catId';

    public function hasAccess()
    {
        return $this->request->isAllowed('ROLES');
    }

    public function defaultAction()
    {
        $this->response->page('ui/roles/categories/view.js', [
            'scope' => $this->request->getScope(),
            'categories' => $this->getList()
        ]);
    }

    /**
     * @param   string  $scope  Filter for UI
     * @return  array
     * @throws  Scalr_Exception_Core
     */
    public function getList($scope = '')
    {
        $criteria = [];
        switch ($this->request->getScope()) {
            case ScopeInterface::SCOPE_SCALR:
                $criteria[] = ['accountId' => NULL];
                break;

            case ScopeInterface::SCOPE_ACCOUNT:
                $criteria[] = ['$or' => [
                    ['accountId' => $this->user->getAccountId()],
                    ['accountId' => NULL]
                ]];
                $criteria[] = ['envId' => NULL];
                break;

            case ScopeInterface::SCOPE_ENVIRONMENT:
                $criteria[] = ['$or' => [
                    ['accountId' => $this->user->getAccountId()],
                    ['accountId' => NULL]
                ]];
                $criteria[] = ['$or' => [
                    ['envId' => NULL],
                    ['envId' => $this->getEnvironmentId(true)]
                ]];
        }

        /* Filter for UI */
        switch ($scope) {
            case ScopeInterface::SCOPE_SCALR:
                $criteria[] = ['accountId' => NULL];
                break;

            case ScopeInterface::SCOPE_ACCOUNT:
                $criteria[] = ['accountId' => $this->user->getAccountId()];
                $criteria[] = ['envId' => NULL];
                break;

            case ScopeInterface::SCOPE_ENVIRONMENT:
                $criteria[] = ['envId' => $this->getEnvironmentId(true)];
                break;

            default:
                break;
        }

        $data = [];
        foreach (RoleCategory::find($criteria) as $category) {
            /* @var $category RoleCategory */
            $s = get_object_vars($category);
            $s['scope'] = $category->getScope();
            $s['used'] = $category->getUsed();
            $s['status'] = $s['used'] ? 'In use' : 'Not used';
            $data[] = $s;
        }

        return $data;
    }

    /**
     * @param   string    $scope
     */
    public function xListAction($scope = '')
    {
        $this->response->data([
            'data' => $this->getList($scope)
        ]);
    }

    /**
     * @param   integer $id
     * @param   string  $name
     * @throws  Exception
     * @throws  Scalr_Exception_Core
     */
    public function xSaveAction($id = 0, $name)
    {
        $this->request->restrictAccess('ROLES', 'MANAGE');

        $validator = new \Scalr\UI\Request\Validator();
        $validator->addErrorIf(!preg_match('/^' . RoleCategory::NAME_REGEXP . '$/', $name), 'name', "Name should start and end with letter or number and contain only letters, numbers, spaces and dashes.");
        $validator->addErrorIf(strlen($name) > RoleCategory::NAME_LENGTH, 'name', "Name should be less than 18 characters");

        $scope = $this->request->getScope();

        $criteria = [
            ['name' => $name]
        ];

        if ($id) {
            $criteria[] = ['id' => ['$ne' => $id]];
        }

        if ($this->user->isScalrAdmin()) {
            $criteria[] = ['accountId' => NULL];
        } else {
            $criteria[] = ['$or' => [['accountId' => $this->user->getAccountId()], ['accountId' => NULL]]];
            if ($scope == 'account') {
                $criteria[] = ['envId' => NULL];
            } else {
                $criteria[] = ['$or' => [['envId' => NULL], ['envId' => $this->getEnvironmentId(true)]]];
            }
        }

        $validator->addErrorIf(RoleCategory::find($criteria)->count(), 'name', 'This name is already in use. Note that Role Categories names are case-insensitive.');

        if (!$validator->isValid($this->response)) {
            return;
        }

        if ($id) {
            $category = RoleCategory::findPk($id);
            /* @var $category RoleCategory */
            if (!$category) {
                throw new Exception('Role Category not found');
            }

            $this->request->checkPermissions($category, true);

            $category->name = $name;

            $category->save();
        } else {
            $category = new RoleCategory();
            if ($this->user->isScalrAdmin()) {
                $category->accountId = NULL;
                $category->envId = NULL;
            } else {
                $category->accountId = $this->user->getAccountId();
                $category->envId = $scope == 'account' ? NULL : $this->getEnvironmentId();
            }

            $category->name = $name;
            $category->save();
        }

        $used = $category->getUsed();
        $this->response->data([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'used' => $used,
                'scope' => $scope,
                'status' => $used ? 'In use' : 'Not used'
            ]
        ]);

        $this->response->success('Role Category successfully saved');
    }

    /**
     * @param JsonData $ids
     * @throws Exception
     */
    public function xGroupActionHandlerAction(JsonData $ids)
    {
        $this->request->restrictAccess('ROLES', 'MANAGE');

        $processed = array();
        $errors = array();

        if (count($ids) == 0) {
            throw new Exception('Empty id\'s list');
        }

        foreach (RoleCategory::find(['id' => ['$in' => $ids]]) as $category) {
            /* @var $category RoleCategory */
            if ($this->request->hasPermissions($category, true)) {
                if ($category->getUsed()) {
                    $errors[] = 'Role category is in use and can\'t be removed.';
                } else {
                    $processed[] = $category->id;
                    $category->delete();
                }
            } else {
                $errors[] = 'Insufficient permissions to remove Role Category';
            }
        }

        $num = count($ids);
        if (count($processed) == $num) {
            $this->response->success('Role Categories successfully removed');
        } else {
            array_walk($errors, function (&$item) {
                $item = '- ' . $item;
            });
            $this->response->warning(sprintf("Successfully removed %d from %d Role Categories. \nFollowing errors occurred:\n%s", count($processed), $num, join($errors, '')));
        }

        $this->response->data(array('processed' => $processed));
    }

}
