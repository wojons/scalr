<?php

use Scalr\Acl\Acl;
use Scalr\Model\Entity\Script;
use Scalr\Model\Entity\ScriptVersion;
use Scalr\Model\Entity\ScriptShortcut;
use Scalr\Model\Entity\Tag;
use Scalr\UI\Request\JsonData;
use Scalr\UI\Request\RawData;
use Scalr\UI\Request\FileUploadData;
use Scalr\UI\Request\Validator;

class Scalr_UI_Controller_Scripts extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'scriptId';

    public function hasAccess()
    {
        return true;
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    /**
     * @param int $scriptId optional script ID
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Scalr_UI_Exception_NotFound
     */
    public function viewAction($scriptId = 0)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ADMINISTRATION_SCRIPTS);

        if ($scriptId) {
            /* @var Script $script */
            $script = Script::findPk($scriptId);
            if (! $script) {
                throw new Scalr_UI_Exception_NotFound();
            }
            $script->checkPermission($this->user, $this->getEnvironmentId(true));

            $data = array(
                'createdByEmail' => $script->createdByEmail,
                'description' => $script->description,
                'dtCreated' => Scalr_Util_DateTime::convertTz($script->dtCreated),
                'dtChanged' => Scalr_Util_DateTime::convertTz($script->dtChanged),
                'name' => $script->name,
                'versions' => array()
            );
            foreach ($script->getVersions() as $version) {
                /* @var ScriptVersion $version */
                $data['versions'][] = array(
                    'content' => $version->content,
                    'version' => $version->version,
                    'dtCreated' => Scalr_Util_DateTime::convertTz($version->dtCreated)
                );
            }

            $this->response->page('ui/scripts/viewcontent.js', array(
                'script' => $data
            ), array('codemirror/codemirror.js'), array('codemirror/codemirror.css'));
        } else {
            $this->response->page('ui/scripts/view.js');
        }
    }

    /**
     * @param int $scriptId
     * @param int $version
     * @throws Scalr_UI_Exception_NotFound
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xGetContentAction($scriptId, $version)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ADMINISTRATION_SCRIPTS);

        /* @var Script $script */
        $script = Script::findPk($scriptId);
        if (! $script)
            throw new Scalr_UI_Exception_NotFound();

        $script->checkPermission($this->user, $this->getEnvironmentId(true));

        $version = $script->getVersion($version);
        if (! $version)
            throw new Scalr_UI_Exception_NotFound();

        $this->response->data(array(
            'content' => $version->content
        ));
    }

    /**
     * Remove scripts
     *
     * @param JsonData $scriptId json array of scriptId to remove
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Scalr_UI_Exception_NotFound
     */
    public function xRemoveAction(JsonData $scriptId)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ADMINISTRATION_SCRIPTS, Acl::PERM_ADMINISTRATION_SCRIPTS_MANAGE);
        $errors = [];

        foreach ($scriptId as $id) {
            try {
                /* @var Script $script */
                $script = Script::findPk($id);
                if (! $script)
                    throw new Scalr_UI_Exception_NotFound();

                $script->checkPermission($this->user, $this->getEnvironmentId(true));

                if (!$script->accountId && ($this->user->getType() != Scalr_Account_User::TYPE_SCALR_ADMIN))
                    throw new Scalr_Exception_InsufficientPermissions();

                $script->delete();
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (count($errors))
            $this->response->warning("Script(s) successfully removed, but some errors occurred:\n" . implode("\n", $errors));
        else
            $this->response->success('Script(s) successfully removed');
    }

    /**
     * @param int $scriptId
     * @param int $version
     * @throws Scalr_UI_Exception_NotFound
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xRemoveVersionAction($scriptId, $version)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ADMINISTRATION_SCRIPTS, Acl::PERM_ADMINISTRATION_SCRIPTS_MANAGE);

        /* @var Script $script */
        $script = Script::findPk($scriptId);
        if (! $script)
            throw new Scalr_UI_Exception_NotFound();

        $script->checkPermission($this->user, $this->getEnvironmentId(true));

        if (!$script->accountId && ($this->user->getType() != Scalr_Account_User::TYPE_SCALR_ADMIN))
            throw new Scalr_Exception_InsufficientPermissions();

        $version = $script->getVersion($version);
        if (! $version)
            throw new Scalr_UI_Exception_NotFound();

        $version->delete();
        $this->response->success();
    }

    /**
     * @param int $id
     * @param string $name
     * @param string $description
     * @param int $isSync
     * @param int $envId optional
     * @param int $timeout optional
     * @param int $version
     * @param RawData $content
     * @param string $tags
     * @param string $uploadType optional
     * @param string $uploadUrl optional
     * @param FileUploadData $uploadFile optional
     * @throws Scalr_UI_Exception_NotFound
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Exception
     */
    public function xSaveAction($id, $name, $description, $isSync = 0, $envId = 2, $timeout = NULL,
                                $version, RawData $content, $tags, $uploadType = NULL, $uploadUrl = NULL, FileUploadData $uploadFile = NULL)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ADMINISTRATION_SCRIPTS, Acl::PERM_ADMINISTRATION_SCRIPTS_MANAGE);
        $validator = new Validator();
        $validator->validate($name, 'name', Validator::NOEMPTY);

        if ($uploadType) {
            $content = false;
            if ($uploadType == 'URL') {
                $content = @file_get_contents($uploadUrl);
                $validator->validate($content, 'uploadUrl', Validator::NOEMPTY, [], 'Invalid source');

            } else if ($uploadType == 'File') {
                $content = $uploadFile;
                $validator->validate($content, 'uploadFile', Validator::NOEMPTY, [], 'Invalid source');

            } else {
                $validator->addError('uploadType', 'Invalid source for script');
            }
        }

        $content = str_replace("\r\n", "\n", $content);
        $tagsResult = [];
        foreach (explode(',', $tags) as $t) {
            $t = trim($t);
            if ($t) {
                if (! preg_match('/^[a-zA-Z0-9-]{3,10}$/', $t))
                    $validator->addError('tags', sprintf('Invalid name for tag: %s', $t));

                $tagsResult[] = $t;
            }
        }

        $tags = $tagsResult;

        if (! $validator->isValid($this->response))
            return;

        /* @var Script $script */
        if ($id) {
            $script = Script::findPk($id);

            if (! $script)
                throw new Scalr_UI_Exception_NotFound();

            $script->checkPermission($this->user, $this->getEnvironmentId(true));

            if (!$script->accountId && ($this->user->getType() != Scalr_Account_User::TYPE_SCALR_ADMIN))
                throw new Scalr_Exception_InsufficientPermissions();
        } else {
            $script = new Script();
            $script->accountId = $this->user->getAccountId() ? $this->user->getAccountId() : NULL;
            $script->createdById = $this->user->getId();
            $script->createdByEmail = $this->user->getEmail();
            $version = 1;
        }

        if ($this->user->isScalrAdmin()) {
            $envId = NULL;
        } else {
            if (! in_array($envId, array_map(function($e) { return $e['id']; }, $this->user->getEnvironments())))
                $envId = NULL;
        }

        $script->name = $name;
        $script->description = $description;
        $script->timeout = $timeout ? $timeout : NULL;
        $script->isSync = $isSync == 1 ? 1 : 0;
        $script->envId = $envId;
        $script->os = (!strncmp($content, '#!cmd', strlen('#!cmd')) || !strncmp($content, '#!powershell', strlen('#!powershell'))) ? Script::OS_WINDOWS : Script::OS_LINUX;
        $script->save();

        $scriptVersion = NULL;
        if ($version) {
            $scriptVersion = $script->getVersion($version);
        }

        if (!$scriptVersion && $script->getLatestVersion()->content !== $content) {
            $scriptVersion = new ScriptVersion();
            $scriptVersion->scriptId = $script->id;
            $scriptVersion->version = $script->getLatestVersion()->version + 1;
        }

        if ($scriptVersion) {
            $scriptVersion->changedById = $this->user->getId();
            $scriptVersion->changedByEmail = $this->user->getEmail();
            $scriptVersion->content = $content;
            $scriptVersion->save();
        }

        if ($this->user->getAccountId())
            Tag::setTags($tags, $this->user->getAccountId(), Tag::RESOURCE_SCRIPT, $script->id);

        $this->response->success('Script successfully saved');
    }


    /**
     * @param int $scriptId
     * @param string $name
     * @throws Scalr_Exception_Core
     * @throws Scalr_UI_Exception_NotFound
     */
    public function xForkAction($scriptId, $name)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ADMINISTRATION_SCRIPTS, Acl::PERM_ADMINISTRATION_SCRIPTS_FORK);

        if (! $name)
            throw new Scalr_Exception_Core('Name cannot be null');

        /* @var Script $script */
        $script = Script::findPk($scriptId);

        if (! $script)
            throw new Scalr_UI_Exception_NotFound();

        $script->checkPermission($this->user, $this->getEnvironmentId(true));
        $script->fork($name, $this->user);
        $this->response->success('Script successfully forked');
    }

    /**
     * @param int $scriptId
     * @throws Scalr_UI_Exception_NotFound
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function editAction($scriptId)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ADMINISTRATION_SCRIPTS, Acl::PERM_ADMINISTRATION_SCRIPTS_MANAGE);

        $vars = Scalr_Scripting_Manager::getScriptingBuiltinVariables();

        /* @var Script $script */
        $script = Script::findPk($scriptId);
        if (! $script)
            throw new Scalr_UI_Exception_NotFound();

        $script->checkPermission($this->user, $this->getEnvironmentId(true));
        if (!$script->accountId && ($this->user->getType() != Scalr_Account_User::TYPE_SCALR_ADMIN))
            throw new Scalr_Exception_InsufficientPermissions();

        $version = $script->getLatestVersion();
        $versionIds = array_map(function($v) { return $v->version; }, $script->getVersions()->getArrayCopy());

        $environments = $this->user->getEnvironments();
        array_unshift($environments, array('id' => 0, 'name' => 'All environments'));

        $this->response->page('ui/scripts/create.js', array(
            'script' => array(
                'id' => $script->id,
                'name' => $script->name,
                'description' => $script->description,
                'envId' => $script->envId ? $script->envId : 0,
                'isSync' => !is_null($script->isSync) ? $script->isSync : 0,
                'timeout' => $script->timeout,
                'content' => $version->content,
                'version' => $version->version,
                'tags' => join(',', Tag::getTags(Tag::RESOURCE_SCRIPT, $script->id))
            ),

            'versions' => $versionIds,
            'timeouts' => $this->getContainer()->config->get('scalr.script.timeout'),
            'environments' => $environments,
            'variables' => "%" . implode("%, %", array_keys($vars)) . "%"

        ), array('codemirror/codemirror.js', 'ux-boxselect.js'), array('codemirror/codemirror.css'));
    }

    public function createAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_ADMINISTRATION_SCRIPTS, Acl::PERM_ADMINISTRATION_SCRIPTS_MANAGE);

        $vars = Scalr_Scripting_Manager::getScriptingBuiltinVariables();
        $environments = $this->user->getEnvironments();
        array_unshift($environments, ['id' => '0', 'name' => 'All environments']);

        $this->response->page('ui/scripts/create.js', array(
            'versions'		=> array(1),
            'variables'		=> "%" . implode("%, %", array_keys($vars)) . "%",
            'timeouts' => $this->getContainer()->config->get('scalr.script.timeout'),
            'environments' => $environments
        ), array('codemirror/codemirror.js', 'ux-boxselect.js'), array('codemirror/codemirror.css'));
    }

    /**
     * @param string $query
     * @param string $origin
     * @param JsonData $sort
     * @param int $start
     * @param int $limit
     */
    public function xListAction($query = null, $origin = null, JsonData $sort, $start = 0, $limit = 20)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ADMINISTRATION_SCRIPTS);

        $criteria = [];
        if ($this->user->isScalrAdmin()) {
            $criteria[] = [ 'accountId' => NULL ];
        } else {
            if ($origin == 'Shared') {
                $criteria[] = [ 'accountId' => NULL ];
            } else if ($origin == 'Custom') {
                $criteria[] = [ 'accountId' => $this->user->getAccountId() ];
                $criteria[] = [ '$or' => [
                    [ 'envId' => $this->getEnvironmentId() ],
                    [ 'envId' => NULL ]
                ]];
            } else {
                $criteria[] = [ '$or' => [
                    [ 'accountId' => $this->user->getAccountId() ],
                    [ 'accountId' => NULL]
                ]];
                $criteria[] = [ '$or' => [
                    [ 'envId' => $this->getEnvironmentId() ],
                    [ 'envId' => NULL ]
                ]];
            }
        }

        if ($query) {
            $querySql = '%' . $query . '%';
            $criteria[] = [
                '$or' => [
                    [ 'id' => [ '$like' => $query ]],
                    [ 'name' => [ '$like' => $querySql ]],
                    [ 'description' => [ '$like' => $querySql ]]
                ]
            ];
        }

        $result = Script::find($criteria, \Scalr\UI\Utils::convertOrder($sort, ['name' => 'ASC'], ['id', 'name', 'description', 'isSync', 'dtCreated', 'dtChanged']), $limit, $start, true);
        $data = [];
        foreach ($result as $script) {
            /* @var Script $script */
            $s = get_object_vars($script);
            $s['dtCreated'] = Scalr_Util_DateTime::convertTz($script->dtCreated);
            $s['dtChanged'] = Scalr_Util_DateTime::convertTz($script->dtChanged);
            $s['version'] = $script->getLatestVersion()->version;
            $data[] = $s;
        }

        $this->response->data([
            'total' => $result->totalNumber,
            'data' => $data
        ]);
    }

    /**
     * @param int $farmId
     * @param int $farmRoleId
     * @param string $serverId
     * @param int $scriptId
     * @param int $shortcutId
     * @throws Exception
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function executeAction($farmId = 0, $farmRoleId = 0, $serverId = '', $scriptId = 0, $shortcutId = 0)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ADMINISTRATION_SCRIPTS, Acl::PERM_ADMINISTRATION_SCRIPTS_EXECUTE);
        $data = ['scripts' => Script::getList($this->user->getAccountId(), $this->getEnvironmentId())];

        if ($shortcutId) {
            /* @var ScriptShortcut $shortcut */
            $shortcut = ScriptShortcut::findPk($shortcutId);

            if (! $shortcut) {
                throw new Exception('Scalr unable to find script execution options for used link');
            }

            if ($shortcut->getScript())
                $shortcut->getScript()->checkPermission($this->user, $this->getEnvironmentId());

            $scriptId = $shortcut->scriptId;
            $farmId = $shortcut->farmId;
            $farmRoleId = $shortcut->farmRoleId;

            $data['scriptId'] = $scriptId;
            $data['scriptPath'] = $shortcut->scriptPath;
            $data['scriptTimeout'] = $shortcut->timeout;
            $data['scriptVersion'] = $shortcut->version;
            $data['scriptIsSync'] = $shortcut->isSync;
            $data['scriptParams'] = $shortcut->params;
            $data['shortcutId'] = $shortcutId;
        }

        $data['farmWidget'] = self::loadController('Farms', 'Scalr_UI_Controller')->getFarmWidget(array(
            'farmId' => (string) $farmId, // TODO: remove (string) and use integer keys for whole project [UI-312]
            'farmRoleId' => (string) $farmRoleId,
            'serverId' => $serverId
        ), array('addAll', 'addAllFarm', 'requiredFarm'));

        $data['scriptId'] = $scriptId;

        $this->response->page('ui/scripts/execute.js', $data);
    }

    /**
     * @param int $farmId
     * @param int $farmRoleId optional
     * @param string $serverId optional
     * @param int $scriptId optional
     * @param string $scriptPath optional
     * @param int $scriptIsSync
     * @param int $scriptTimeout
     * @param int $scriptVersion
     * @param array $scriptParams optional
     * @param int $shortcutId optional
     * @param int $editShortcut optional
     * @throws Exception
     */
    public function xExecuteAction($farmId, $farmRoleId = 0, $serverId = '', $scriptId = 0, $scriptPath = '', $scriptIsSync, $scriptTimeout, $scriptVersion, array $scriptParams = [], $shortcutId = null, $editShortcut = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ADMINISTRATION_SCRIPTS, Acl::PERM_ADMINISTRATION_SCRIPTS_EXECUTE);

        if ($serverId) {
            $dbServer = DBServer::LoadByID($serverId);
            $this->user->getPermissions()->validate($dbServer);

            $target = Script::TARGET_INSTANCE;
            $serverId = $dbServer->serverId;
            $farmRoleId = $dbServer->farmRoleId;
            $farmId = $dbServer->farmId;

        } else if ($farmRoleId) {
            $dbFarmRole = DBFarmRole::LoadByID($farmRoleId);
            $this->user->getPermissions()->validate($dbFarmRole);

            $target = Script::TARGET_ROLE;
            $farmRoleId = $dbFarmRole->ID;
            $farmId = $dbFarmRole->FarmID;

        } else if (!$farmId) {
            $target = Script::TARGET_ALL;
        } else {
            $dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
            $this->user->getPermissions()->validate($dbFarm);

            $target = Script::TARGET_FARM;
            $farmId = $dbFarm->ID;
        }

        if ($scriptId) {
            $script = Script::findPk($scriptId);
            /* @var Script $script */
            if (! $script) {
                throw new Scalr_UI_Exception_NotFound();
            }
            $script->checkPermission($this->user, $this->getEnvironmentId());
        } elseif (! $scriptPath) {
            throw new Scalr_Exception_Core('scriptId or scriptPath should be set');
        }

        if (! $scriptTimeout) {
            $scriptTimeout = $scriptIsSync == 1 ? Scalr::config('scalr.script.timeout.sync') : Scalr::config('scalr.script.timeout.async');
        }

        $executeScript = true;

        if ($shortcutId && ($target != Script::TARGET_INSTANCE || $target != Script::TARGET_ALL)) {
            if ($shortcutId == -1) {
                $shortcut = new ScriptShortcut();
                $shortcut->farmId = $farmId;
            } else {
                $shortcut = ScriptShortcut::findPk($shortcutId);
                /* @var ScriptShortcut $shortcut */
                if (! $shortcut) {
                    throw new Scalr_UI_Exception_NotFound();
                }

                if ($editShortcut == 1)
                    $executeScript = false;
            }

            $shortcut->farmRoleId = $farmRoleId == 0 ? NULL : $farmRoleId;

            if ($scriptId) {
                $shortcut->scriptId = $scriptId;
                $shortcut->scriptPath = '';
            } else {
                $shortcut->scriptPath = $scriptPath;
                $shortcut->scriptId = NULL;
            }

            $shortcut->isSync = $scriptIsSync;
            $shortcut->version = $scriptVersion;
            $shortcut->timeout = $scriptTimeout;
            $shortcut->params = $scriptParams;
            $shortcut->save();
        }

        if ($executeScript) {
            switch($target) {
                case Script::TARGET_FARM:
                    $servers = $this->db->GetAll("SELECT server_id FROM servers WHERE status IN (?,?) AND farm_id=?",
                        array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $farmId)
                    );
                    break;
                case Script::TARGET_ROLE:
                    $servers = $this->db->GetAll("SELECT server_id FROM servers WHERE status IN (?,?) AND farm_roleid=?",
                        array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $farmRoleId)
                    );
                    break;
                case Script::TARGET_INSTANCE:
                    $servers = $this->db->GetAll("SELECT server_id FROM servers WHERE status IN (?,?) AND server_id=?",
                        array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $serverId)
                    );
                    break;
                case Script::TARGET_ALL:
                    $servers = $this->db->GetAll("SELECT server_id FROM servers WHERE status IN (?,?) AND env_id = ?",
                        array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $this->getEnvironmentId())
                    );
                    break;
            }

            $scriptSettings = array(
                'version' => $scriptVersion,
                'timeout' => $scriptTimeout,
                'issync' => $scriptIsSync,
                'params' => serialize($scriptParams)
            );

            if ($scriptId) {
                $scriptSettings['scriptid'] = $scriptId;
                $scriptSettings['type'] = Scalr_Scripting_Manager::ORCHESTRATION_SCRIPT_TYPE_SCALR;
            }
            else {
                $scriptSettings['script_path'] = $scriptPath;
                $scriptSettings['type'] = Scalr_Scripting_Manager::ORCHESTRATION_SCRIPT_TYPE_LOCAL;
            }

            $serializer = Scalr_Messaging_JsonSerializer::getInstance();
            $cryptoTool = Scalr_Messaging_CryptoTool::getInstance();

            // send message to start executing task (starts script)
            if (count($servers) > 0) {
                foreach ($servers as $server) {
                    $DBServer = DBServer::LoadByID($server['server_id']);

                    $msg = new Scalr_Messaging_Msg_ExecScript("Manual");
                    $msg->setServerMetaData($DBServer);

                    $script = Scalr_Scripting_Manager::prepareScript($scriptSettings, $DBServer);

                    $itm = new stdClass();
                    // Script
                    $itm->asynchronous = ($script['issync'] == 1) ? '0' : '1';
                    $itm->timeout = $script['timeout'];

                    if ($script['body']) {
                        $itm->name = $script['name'];
                        $itm->body = $script['body'];
                    } else {
                        $itm->path = $script['path'];
                        $itm->name = "local-".crc32($script['path']).mt_rand(100, 999);
                    }
                    $itm->executionId = $script['execution_id'];

                    $msg->scripts = array($itm);
                    $msg->setGlobalVariables($DBServer, true);

                    /*
                    if ($DBServer->IsSupported('2.5.12')) {
                        $DBServer->scalarizr->system->executeScripts(
                            $msg->scripts,
                            $msg->globalVariables,
                            $msg->eventName,
                            $msg->roleName
                        );
                    } else
                        */$DBServer->SendMessage($msg, false, true);
                }
            }

            $this->response->success('Script execution has been queued and will occur on the selected instance(s) within a couple of minutes.');
        } else {
            $this->response->success('Script shortcut successfully saved');
        }
    }
}
