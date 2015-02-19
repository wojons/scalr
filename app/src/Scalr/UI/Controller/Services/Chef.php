<?php

use Scalr\Model\Entity\ChefServer;

class Scalr_UI_Controller_Services_Chef extends Scalr_UI_Controller
{

    public function hasAccess()
    {
        return true;
    }

    /**
     * @param int $servId
     * @param string $chefEnv
     */
    public function xListAllRecipesAction($servId, $chefEnv)
    {
        $chefClient = $this->getChefClient($servId);

        $recipes = [];
        $response = (array)$chefClient->listCookbooks($chefEnv || $chefEnv == '_default' ? '' : $chefEnv);

        foreach ($response as $key => $value) {
            $recipeList = (array)$chefClient->listRecipes($key, '_latest');

            foreach ($recipeList as $name => $recipeValue) {
                if ($name == 'recipes') {
                    foreach ($recipeValue as $recipe) {
                        $recipes[] = [
                            'cookbook' => $key,
                            'name' => substr($recipe->name, 0, (strlen($recipe->name)-3))
                        ];
                    }
                }
            }
        }
        sort($recipes);

        $this->response->data(array(
            'data' => $recipes
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
            $role = $chefClient->getRole($key);
            $roles[] = [
                'name' => $role->name,
                'chef_type' => $role->chef_type
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
                ['$and' => [['accountId' => $this->user->getAccountId()], ['envId' => $this->getEnvironmentId()], ['level' => ChefServer::LEVEL_ENVIRONMENT]]],
                ['$and' => [['accountId' => $this->user->getAccountId()], ['envId' => null], ['level' => ChefServer::LEVEL_ACCOUNT]]],
                ['$and' => [['accountId' => null], ['envId' => null], ['level' => ChefServer::LEVEL_SCALR]]]
            ]];
        }
        $server = ChefServer::findOne($criteria);

        if(!$server)
            throw new Scalr_Exception_InsufficientPermissions();

        return Scalr_Service_Chef_Client::getChef($server->url, $server->username, $this->getCrypto()->decrypt($server->authKey));
    }


}