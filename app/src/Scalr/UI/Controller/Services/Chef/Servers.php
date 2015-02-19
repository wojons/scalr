<?php

use Scalr\Acl\Acl;
use Scalr\UI\Request\JsonData;
use Scalr\UI\Request\Validator;
use Scalr\Model\Entity\ChefServer;

class Scalr_UI_Controller_Services_Chef_Servers extends Scalr_UI_Controller
{
    private static $levelMap = [
        'environment' => ChefServer::LEVEL_ENVIRONMENT,
        'account'     => ChefServer::LEVEL_ACCOUNT,
        'scalr'       => ChefServer::LEVEL_SCALR
    ];

    var $level = null;

    public function hasAccess()
    {
        return true;
    }
    
    public function init()
    {
        if ($this->user->isScalrAdmin()) {
            $level = 'scalr';
        } else {
            $level = $this->getParam('level') ? $this->getParam('level') : 'environment';
        }
        if (isset(self::$levelMap[$level])) {
            $this->level = self::$levelMap[$level];
        } elseif (in_array($level, [ChefServer::LEVEL_ENVIRONMENT, ChefServer::LEVEL_ACCOUNT, ChefServer::LEVEL_SCALR])) {
            $this->level = (int)$level;
        } else {
            throw new Scalr_Exception_Core('Invalid chef servers scope');
        }
    }

    private function canManageServers()
    {
        return ($this->level == ChefServer::LEVEL_ENVIRONMENT && $this->request->isAllowed(Acl::RESOURCE_SERVICES_ENVADMINISTRATION_CHEF) && !$this->user->isScalrAdmin() ||
               $this->level == ChefServer::LEVEL_ACCOUNT && $this->request->isAllowed(Acl::RESOURCE_SERVICES_ADMINISTRATION_CHEF) && !$this->user->isScalrAdmin() ||
               $this->level == ChefServer::LEVEL_SCALR && $this->user->isScalrAdmin());
    }

    /**
     * @param ChefServer $server
     */
    private function canEditServer($server)
    {
        return $this->level == $server->level &&
               ($server->level == ChefServer::LEVEL_ENVIRONMENT && $server->envId == $this->getEnvironmentId() && $server->accountId == $this->user->getAccountId() ||
               $server->level == ChefServer::LEVEL_ACCOUNT && $server->accountId == $this->user->getAccountId() && empty($server->envId) ||
               $server->level == ChefServer::LEVEL_SCALR && empty($server->accountId) && empty($server->envId));
    }
    
    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        if (!$this->canManageServers()) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $this->response->page('ui/services/chef/servers/view.js', [
            'servers' => $this->getList(),
            'level' => $this->level,
            'levelMap' => array_flip(self::$levelMap)
        ]);
    }

    public function xListAction()
    {
        if (!$this->canManageServers()) {
            throw new Scalr_Exception_InsufficientPermissions();
        }
    
        $this->response->data([
            'servers' => $this->getList()
        ]);
    }

    //with governance
    public function xListServersAction()
    {
        if (!$this->user->isAdmin()) {
            $governance = new Scalr_Governance($this->getEnvironmentId());
            $limits = $governance->getValue(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_CHEF, null);
        }
        $list = [];
        $levelMap = array_flip(self::$levelMap);
        foreach ($this->getList() as $server) {
            if (!$limits || isset($limits['servers'][(string)$server['id']])) {
                $list[] = [
                    'id'       => (string)$server['id'],
                    'url'      => $server['url'],
                    'username' => $server['username'],
                    'level'    => $levelMap[$server['level']]
                ];
            }
        }
        $this->response->data([
            'data' => $list
        ]);
    }

    /**
     * @param int $id
     * @throws Exception
     */
    public function xRemoveAction($id)
    {
        if (!$this->canManageServers()) {
            throw new Scalr_Exception_InsufficientPermissions();
        }
        
        $server = ChefServer::findPk($id);
        
        if (!$this->canEditServer($server)) {
            throw new Scalr_Exception_Core('Insufficient permissions to remove chef server.');
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
        if (!$this->canManageServers()) {
            throw new Scalr_Exception_InsufficientPermissions();
        }
        
        if (!$id) {
            $server = new ChefServer();
            $server->level = $this->level;
            if ($this->level == ChefServer::LEVEL_ENVIRONMENT) {
                $server->accountId = $this->user->getAccountId();
                $server->envId = $this->getEnvironmentId();
            } elseif ($this->level == ChefServer::LEVEL_ACCOUNT) {
                $server->accountId = $this->user->getAccountId();
            }
        } else {
            $server = ChefServer::findPk($id);
            if (!$this->canEditServer($server)) {
                throw new Scalr_Exception_Core('Insufficient permissions to edit chef server at this scope');
            }
        }

        $validator = new Validator();
        $validator->validate($url, 'url', Validator::NOEMPTY);

        //check url unique within current level
        $criteria = [];
        $criteria[] = ['url' => $url];
        $criteria[] = ['level' => $this->level];
        if ($server->id) {
            $criteria[] = ['id' => ['$ne' => $server->id]];
        }
        if ($this->level == ChefServer::LEVEL_ENVIRONMENT) {
            $criteria[] = ['envId' => $server->envId];
            $criteria[] = ['accountId' => $server->accountId];
        } elseif ($this->level == ChefServer::LEVEL_ACCOUNT) {
            $criteria[] = ['envId' => null];
            $criteria[] = ['accountId' => $server->accountId];
        } elseif ($this->level == ChefServer::LEVEL_SCALR) {
            $criteria[] = ['envId' => null];
            $criteria[] = ['accountId' => null];
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
        if (!$this->canManageServers()) {
            throw new Scalr_Exception_InsufficientPermissions();
        }
        
        $processed = array();
        $errors = array();
        if (!empty($serverIds)) {
            $servers = ChefServer::find(array(
                array(
                    'id'  => array('$in' => $serverIds)
                )
            ));
            foreach($servers as $server) {
                if (!$this->canEditServer($server)) {
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
            array_walk($errors, function(&$item) { $item = '- ' . $item; });
            $this->response->warning(sprintf("Successfully removed %d from %d chef servers. \nFollowing errors occurred:\n%s", count($processed), $num, join($errors, '')));
        }

        $this->response->data(array('processed' => $processed));
    }


    private function getList()
    {
        $list = ChefServer::getList($this->user->getAccountId(), $this->getEnvironmentId(true), $this->level);
        $data = [];
        foreach ($list as $entity) {
            $data[] = $this->getServerData($entity);
        }

        return $data;
    }

    private function getServerData($server)
    {
        $data = [
            'id'       => $server->id,
            'url'      => $server->url,
            'username' => $server->username,
            'status'   => $server->isInUse($this->user->getAccountId(), $this->getEnvironmentId(true), $this->level),
            'level'    => $server->level
        ];
        if ($this->level == $server->level) {
            $data['authKey'] = $this->getCrypto()->decrypt($server->authKey);
            $data['vUsername'] = $server->vUsername;
            $data['vAuthKey'] = $this->getCrypto()->decrypt($server->vAuthKey);
        }

        return $data;
    }
}