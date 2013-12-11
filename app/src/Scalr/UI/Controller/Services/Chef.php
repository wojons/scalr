<?php

class Scalr_UI_Controller_Services_Chef extends Scalr_UI_Controller
{
    
    public function xListRunlistAction()
    {
        $this->request->defineParams(array(
            'serverId' => array('type' => 'int'),
            'chefEnvironment'
        ));

        $sql = 'SELECT id, name, description, chef_server_id as servId, chef_environment as chefEnv FROM services_chef_runlists WHERE env_id = ?';
        $params = array($this->getEnvironmentId());

        if ($this->getParam('serverId')) {
            $sql .= ' AND chef_server_id = ?';
            $params[] = $this->getParam('serverId');
        }

        if ($this->getParam('chefEnvironment')) {
            $sql .= ' AND chef_environment = ?';
            $params[] = $this->getParam('chefEnvironment');
        }

        $this->response->data(array(
            'data' => $this->db->GetAll($sql, $params)
        ));
    }

    public function xListAllRecipesAction()
    {
        $chefClient = $this->getChefClient($this->getParam('servId'));
        
        $this->response->data(array(
            'data' => $this->listRecipes($chefClient, $this->getParam('chefEnv'))
        ));
    }

    public function xListRolesAction()
    {
        $chefClient = $this->getChefClient($this->getParam('servId'));

        $roles = $this->listRoles($chefClient);
        //array_unshift($roles, array('name' => ''));
        
        $this->response->data(array(
            'data' => $roles
        ));
    }

    public function xListAllAction()
    {
        $chefClient = $this->getChefClient($this->getParam('servId'));

        $roles = $this->listRoles($chefClient);
        $recipes = $this->listRecipes($chefClient, $this->getParam('chefEnv'));
        
        $this->response->data(array(
            'data' => array_merge($roles, $recipes)
        ));
    }

    private function getChefClient($chefServerId)
    {
        $server = $this->db->GetRow('SELECT url, username, auth_key FROM services_chef_servers WHERE id = ?', array($chefServerId));
        return Scalr_Service_Chef_Client::getChef($server['url'], $server['username'], $this->getCrypto()->decrypt($server['auth_key'], $this->cryptoKey));
    }
    
    private function listRecipes(&$chefClient, $chefEnv)
    {
        $recipes = array();
        $response = (array)$chefClient->listCookbooks($chefEnv || $chefEnv == '_default' ? '' : $chefEnv);

        foreach ($response as $key => $value) {
            $recipeList = (array)$chefClient->listRecipes($key, '_latest');

            foreach ($recipeList as $name => $recipeValue) {
                if ($name == 'recipes') {
                    foreach ($recipeValue as $recipe) {
                        $recipes[] = array(
                            'cookbook' => $key,
                            'name' => substr($recipe->name, 0, (strlen($recipe->name)-3))
                        );
                    }
                }
            }
        }
        sort($recipes);
        return $recipes;
    }

    private function listRoles(&$chefClient)
    {
        $roles = array();
        $response = (array)$chefClient->listRoles();

        foreach ($response as $key => $value) {
            $role = $chefClient->getRole($key);
            $roles[] = array(
                'name' => $role->name,
                'chef_type' => $role->chef_type
            );
        }
        sort($roles);
        return $roles;
    }
}