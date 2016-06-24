<?php

use Scalr\Acl\Acl;
use Scalr\UI\Request\JsonData;
use Scalr\UI\Request\Validator;
use Scalr\Model\Entity\ChefServer;

class Scalr_UI_Controller_Services_Chef_Servers extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return true;
    }

    /**
     * Read only access
     *
     * @return bool
     */
    private function canViewServers()
    {
        return ($this->request->getScope() == ChefServer::SCOPE_ENVIRONMENT && $this->request->isAllowed(Acl::RESOURCE_SERVICES_CHEF_ENVIRONMENT) && !$this->user->isScalrAdmin() ||
            $this->request->getScope() == ChefServer::SCOPE_ACCOUNT && $this->request->isAllowed(Acl::RESOURCE_SERVICES_CHEF_ACCOUNT) && !$this->user->isScalrAdmin() ||
            $this->request->getScope() == ChefServer::SCOPE_SCALR && $this->user->isScalrAdmin());
    }

    /**
     * Full access
     *
     * @param ChefServer $server
     * @return bool
     */
    private function canManageServers($server)
    {
        return $this->request->getScope() == $server->getScope() &&
            ($server->getScope() == ChefServer::SCOPE_SCALR ||

            $server->getScope() == ChefServer::SCOPE_ACCOUNT && $server->accountId == $this->user->getAccountId() &&
                $this->request->isAllowed(Acl::RESOURCE_SERVICES_CHEF_ACCOUNT, Acl::PERM_SERVICES_CHEF_ACCOUNT_MANAGE) ||

            $server->getScope() == ChefServer::SCOPE_ENVIRONMENT && $server->envId == $this->getEnvironmentId(true) && $server->accountId == $this->user->getAccountId() &&
                $this->request->isAllowed(Acl::RESOURCE_SERVICES_CHEF_ENVIRONMENT, Acl::PERM_SERVICES_CHEF_ENVIRONMENT_MANAGE));
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        if (!$this->canViewServers()) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $this->response->page('ui/services/chef/servers/view.js', [
            'servers' => $this->getList()
        ]);
    }

    public function xListAction()
    {
        if (!$this->canViewServers()) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $this->response->data([
            'servers' => $this->getList()
        ]);
    }

    /**
     * @param int $id
     * @throws Exception
     */
    public function xRemoveAction($id)
    {
        $server = ChefServer::findPk($id);
        /* @var $server ChefServer */
        if (!$this->canManageServers($server)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        if ($server->isInUse()) {
            throw new Scalr_Exception_Core('Chef server is in use and can\'t be removed.');
        }

        $server->delete();
        $this->response->success("Chef server successfully removed.");
    }

    /**
     * @param int $id
     * @param string $url
     * @param string $username
     * @param string $authKey
     * @param string $vUsername
     * @param string $vAuthKey
     * @throws Exception
     */
    public function xSaveAction($id, $url, $username, $authKey, $vUsername, $vAuthKey)
    {
        if (!$id) {
            $server = new ChefServer();
            $server->setScope($this->request->getScope(), $this->user->getAccountId(), $this->getEnvironmentId(true));
        } else {
            $server = ChefServer::findPk($id);
            /* @var $server ChefServer */
            if (!$this->canManageServers($server)) {
                throw new Scalr_Exception_InsufficientPermissions();
            }
        }

        $validator = new Validator();
        $validator->validate($url, 'url', Validator::NOEMPTY);

        //check url unique within current scope
        $criteria = [];
        $criteria[] = ['url' => $url];
        if ($server->id) {
            $criteria[] = ['id' => ['$ne' => $server->id]];
        }
        switch ($this->request->getScope()) {
            case ChefServer::SCOPE_ENVIRONMENT:
                $criteria[] = ['level' => ChefServer::LEVEL_ENVIRONMENT];
                $criteria[] = ['envId' => $server->envId];
                $criteria[] = ['accountId' => $server->accountId];
                break;
            case ChefServer::SCOPE_ACCOUNT:
                $criteria[] = ['level' => ChefServer::LEVEL_ACCOUNT];
                $criteria[] = ['envId' => null];
                $criteria[] = ['accountId' => $server->accountId];
                break;
            case ChefServer::SCOPE_SCALR:
                $criteria[] = ['level' => ChefServer::LEVEL_SCALR];
                $criteria[] = ['envId' => null];
                $criteria[] = ['accountId' => null];
                break;
        }

        if (ChefServer::findOne($criteria)) {
            $validator->addError('url', 'Url must be unique within current scope');
        }

        if (!$validator->isValid($this->response))
            return;


        $authKey = str_replace("\r\n", "\n", $authKey);
        $vAuthKey = str_replace("\r\n", "\n", $vAuthKey);

        $server->url = $url;
        $server->username = $username;
        $server->vUsername = $vUsername;
        $server->authKey = $this->getCrypto()->encrypt($authKey);
        $server->vAuthKey = $this->getCrypto()->encrypt($vAuthKey);

        $chef = Scalr_Service_Chef_Client::getChef($server->url, $server->username, $authKey);
        $response = $chef->listCookbooks();
        $chef2 = Scalr_Service_Chef_Client::getChef($server->url, $server->vUsername, $vAuthKey);
        $clientName = 'scalr-temp-client-' . rand(10000, 99999);
        $response = $chef2->createClient($clientName);
        $response2 = $chef->removeClient($clientName);

        $server->save();

        $this->response->data(array(
            'server' => $this->getServerData($server)
        ));
        $this->response->success('Chef server successfully saved');
    }

    /**
     * @param JsonData $serverIds
     * @throws Exception
     */
    public function xGroupActionHandlerAction(JsonData $serverIds)
    {
        $processed = [];
        $errors = [];

        if (!empty($serverIds)) {
            $servers = ChefServer::find([['id' => ['$in' => $serverIds]]]);
            foreach($servers as $server) {
                /* @var $server ChefServer */
                if (!$this->canManageServers($server)) {
                    $errors[] = 'Insufficient permissions to remove chef server';
                } elseif ($server->isInUse()) {
                    $errors[] = 'Chef server is in use and can\'t be removed.';
                } else {
                    $processed[] = $server->id;
                    $server->delete();
                }
            }
        }

        $num = count($serverIds);
        if (count($processed) == $num) {
            $this->response->success('Chef servers successfully removed');
        } else {
            array_walk($errors, function (&$item) {
                $item = '- ' . $item;
            });
            $this->response->warning(sprintf("Successfully removed %d from %d chef servers. \nFollowing errors occurred:\n%s", count($processed), $num, join($errors, '')));
        }

        $this->response->data(array('processed' => $processed));
    }


    private function getList()
    {
        $list = ChefServer::getList($this->user->getAccountId(), $this->getEnvironmentId(true), $this->request->getScope());
        $data = [];

        foreach ($list as $entity) {
            $data[] = $this->getServerData($entity);
        }

        return $data;
    }

    /**
     * @param ChefServer $server
     * @return array
     */
    private function getServerData($server)
    {
        $data = [
            'id'       => $server->id,
            'url'      => $server->url,
            'username' => $server->username,
            'status'   => $server->isInUse($this->user->getAccountId(), $this->getEnvironmentId(true), $this->request->getScope()),
            'scope'    => $server->getScope()
        ];
        if ($this->request->getScope() == $server->getScope()) {
            $data['authKey'] = $this->getCrypto()->decrypt($server->authKey);
            $data['vUsername'] = $server->vUsername;
            $data['vAuthKey'] = $this->getCrypto()->decrypt($server->vAuthKey);
        }

        return $data;
    }
}