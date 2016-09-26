<?php

use Scalr\Acl\Acl;
use Scalr\Model\Entity\ChefServer;

class Scalr_UI_Controller_Services_Chef extends Scalr_UI_Controller
{

    public function hasAccess()
    {
        return $this->request->isFarmDesignerAllowed() || //farmdesigner
               $this->request->isAllowed('ROLES', 'MANAGE') || //roleeditor
               $this->request->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_BUILD) || //rolebuilder
               $this->request->isAllowed(Acl::RESOURCE_GOVERNANCE_ENVIRONMENT);//governance
    }

    /**
     * Returns list of roles and recipes
     * 
     * @param int $servId
     * @param string $chefEnv
     */
    public function xListRolesRecipesAction($servId, $chefEnv)
    {
        $chefClient = $this->getChefClient($servId);

        $result = [];

        $response = (array)$chefClient->listEnvironmentRecipes($chefEnv);
        foreach ($response as $recipe) {
            $chunks = explode("::", $recipe);
            if (count($chunks) == 1) {
                $result[] = [
                    'id' => $chunks[0] . '::default',
                    'type' => 'recipe'
                ];
            } else {
                $result[] = [
                    'id' => $chunks[0] . '::' . $chunks[1],
                    'type' => 'recipe'
                ];
            }
        }

        $response = (array)$chefClient->listRoles();
        foreach ($response as $key => $value) {
            $result[] = [
                'id' => $key,
                'type' => 'role'
            ];
        }

        $this->response->data(array(
            'data' => $result
        ));
    }

    /**
     * @param int $servId
     */
    public function xListRolesAction($servId)
    {
        $chefClient = $this->getChefClient($servId);

        $roles = [];
        $response = (array)$chefClient->listRoles();

        foreach ($response as $key => $value) {
            $roles[] = [
                'id' => $key,
                'name' => $key,
                'chef_type' => 'role'
            ];
        }
        sort($roles);

        $this->response->data(array(
            'data' => $roles
        ));
    }

    /**
     * @param int $servId
     * @throws Exception
     */
    public function xListEnvironmentsAction($servId)
    {
        $chefClient = $this->getChefClient($servId);

        $environments = [];
        $response = $chefClient->listEnvironments();
        if ($response instanceof stdClass) {
            $response = (array)$response;
        }

        foreach ($response as $key => $value) {
            $environments[]['name'] = $key;
        }

        $this->response->data(array('data' => $environments));
    }

    /**
     * @param int $servId
     * @param string $chefEnv
     */
    private function getChefClient($servId)
    {
        $criteria[] = ['id' => $servId];
        if ($this->user->isAdmin()) {
            $criteria[] = ['accountId' => null];
            $criteria[] = ['envId' => null];
            $criteria[] = ['level' => ChefServer::LEVEL_SCALR];
        } else {
            $criteria[] = ['$or' => [
                ['$and' => [['accountId' => $this->user->getAccountId()], ['envId' => $this->getEnvironmentId(true)], ['level' => ChefServer::LEVEL_ENVIRONMENT]]],
                ['$and' => [['accountId' => $this->user->getAccountId()], ['envId' => null], ['level' => ChefServer::LEVEL_ACCOUNT]]],
                ['$and' => [['accountId' => null], ['envId' => null], ['level' => ChefServer::LEVEL_SCALR]]]
            ]];
        }
        $server = ChefServer::findOne($criteria);

        if(!$server)
            throw new Scalr_Exception_InsufficientPermissions();

        return Scalr_Service_Chef_Client::getChef($server->url, $server->username, $this->getCrypto()->decrypt($server->authKey));
    }

    /**
     * Returns list of chef servers applying governance
     *
     * @return array
     */
    public function xListServersAction()
    {
        $limits = null;

        if (!$this->user->isAdmin()) {
            $governance = new Scalr_Governance($this->getEnvironmentId(true));
            $limits = $governance->getValue(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_CHEF, null);
        }

        $list = [];

        foreach (ChefServer::getList($this->user->getAccountId(), $this->getEnvironmentId(true), $this->request->getScope()) as $server) {
            if (!$limits || isset($limits['servers'][(string)$server->id])) {
                $list[] = [
                    'id'       => (string)$server->id,
                    'url'      => $server->url,
                    'username' => $server->username,
                    'scope'    => $server->getScope()
                ];
            }
        }

        $this->response->data([
            'data' => $list
        ]);
    }
}