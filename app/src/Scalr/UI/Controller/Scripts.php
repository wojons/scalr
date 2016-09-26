<?php

use Scalr\Acl\Acl;
use Scalr\Model\Entity\OrchestrationLogManualScript;
use Scalr\Model\Entity\Script;
use Scalr\Model\Entity\ScriptVersion;
use Scalr\Model\Entity\ScriptShortcut;
use Scalr\Model\Entity\Tag;
use Scalr\Model\Entity;
use Scalr\UI\Request\JsonData;
use Scalr\UI\Request\RawData;
use Scalr\UI\Request\FileUploadData;
use Scalr\UI\Request\Validator;
use Scalr\UI\Utils;
use Scalr\DataType\ScopeInterface;

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

    public function viewAction()
    {
        $this->request->restrictAccess('SCRIPTS');

        $vars = Scalr_Scripting_Manager::getScriptingBuiltinVariables();
        $environments = $this->user->getEnvironments();

        $this->response->page('ui/scripts/view.js', array(
            'variables' => "%" . implode("%, %", array_keys($vars)) . "%",
            'timeouts' => $this->getContainer()->config->get('scalr.script.timeout'),
            'environments' => $environments,
            'scope' => $this->request->getScope()
        ), array('codemirror/codemirror.js'), array('codemirror/codemirror.css'));
    }

    /**
     * @param Script $script
     * @return array
     */
    protected function getScriptInfo($script)
    {
        $result = [
            'versions' => [],
            'tags' => join(',', Tag::getTags(Tag::RESOURCE_SCRIPT, $script->id))
        ];

        foreach ($script->getVersions(true) as $version) {
            /** var ScriptVersion $version */
            $result['versions'][] = [
                'version' => $version->version,
                'variables' => $version->variables,
                'dtCreated' => Scalr_Util_DateTime::convertTz($version->dtCreated),
                'content' => $version->content
            ];
            $result['version'] = $version->version;
        }

        return $result;
    }

    /**
     * @param int $scriptId
     */
    public function xGetAction($scriptId)
    {
        $this->request->restrictAccess('SCRIPTS');

        /* @var $script Script */
        $script = Script::findPk($scriptId);
        if (! $script)
            throw new Scalr_UI_Exception_NotFound();

        $script->checkPermission($this->user, $this->getEnvironmentId(true));

        $this->response->data([
            'script' => $this->getScriptInfo($script)
        ]);
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
        $this->request->isAllowed('SCRIPTS', 'MANAGE');

        $errors = [];
        $processed = [];

        foreach ($scriptId as $id) {
            try {
                /* @var $script Script */
                $script = Script::findPk($id);
                if (! $script)
                    throw new Scalr_UI_Exception_NotFound();

                $script->checkPermission($this->user, $this->getEnvironmentId(true));

                if (!$script->accountId && ($this->user->getType() != Scalr_Account_User::TYPE_SCALR_ADMIN))
                    throw new Scalr_Exception_InsufficientPermissions();

                $script->delete();
                $processed[] = $id;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        $this->response->data(['processed' => $processed]);
        if (count($errors))
            $this->response->warning("Selected script(s) successfully removed, but some errors occurred:\n" . implode("\n", $errors));
        else
            $this->response->success('Selected script(s) successfully removed');
    }

    /**
     * @param int $scriptId
     * @param int $version
     * @throws Scalr_UI_Exception_NotFound
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xRemoveVersionAction($scriptId, $version)
    {
        $this->request->restrictAccess('SCRIPTS', 'MANAGE');

        /* @var $script Script */
        $script = Script::findPk($scriptId);
        if (! $script)
            throw new Scalr_UI_Exception_NotFound();

        $script->checkPermission($this->user, $this->getEnvironmentId(true));

        if (!$script->accountId && ($this->user->getType() != Scalr_Account_User::TYPE_SCALR_ADMIN))
            throw new Scalr_Exception_InsufficientPermissions();

        if ($script->getVersions()->count() == 1) {
            throw new Exception('You can\'t delete the last version.');
        }

        $version = $script->getVersion($version);
        if (! $version)
            throw new Scalr_UI_Exception_NotFound();

        $version->delete();
        $script->getVersions(true); // reset cache
        $this->response->success();
        $this->response->data(['script' => $this->getScriptInfo($script)]);
    }

    /**
     * @param int $id
     * @param string $name
     * @param string $description
     * @param int $isSync
     * @param bool $allowScriptParameters
     * @param int $envId optional
     * @param int $timeout optional
     * @param int $version
     * @param RawData $content
     * @param string $tags
     * @param string $uploadType optional
     * @param string $uploadUrl optional
     * @param FileUploadData $uploadFile optional
     * @param bool $checkScriptParameters optional
     * @throws Scalr_UI_Exception_NotFound
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Exception
     */
    public function xSaveAction($id, $name, $description, $isSync = 0, $allowScriptParameters = false, $envId = NULL, $timeout = NULL,
                                $version, RawData $content, $tags, $uploadType = NULL, $uploadUrl = NULL, FileUploadData $uploadFile = NULL, $checkScriptParameters = false)
    {
        $this->request->restrictAccess('SCRIPTS', 'MANAGE');

        $validator = new Validator();
        $validator->validate($name, 'name', Validator::NOEMPTY);

        if ($uploadType && $uploadType == 'URL') {
            $validator->validate($uploadUrl, 'uploadUrl', Validator::URL);

            if (!$validator->isValid($this->response))
                return;
        }

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

        $envId = $this->getEnvironmentId(true);

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

        $criteria = [];
        $criteria[] = ['name' => $name];
        if ($id) {
            $criteria[] = ['id' => ['$ne' => $id]];
        }
        switch ($this->request->getScope()) {
            case Script::SCOPE_ENVIRONMENT:
                $criteria[] = ['envId' => $envId];
                $criteria[] = ['accountId' => $this->user->getAccountId()];
                break;
            case Script::SCOPE_ACCOUNT:
                $criteria[] = ['envId' => null];
                $criteria[] = ['accountId' => $this->user->getAccountId()];
                break;
            case Script::SCOPE_SCALR:
                $criteria[] = ['envId' => null];
                $criteria[] = ['accountId' => null];
                break;
        }

        if (Script::findOne($criteria)) {
            $validator->addError('name', 'Script name must be unique within current scope');
        }

        if (!$validator->isValid($this->response))
            return;

        /* @var $script Script */
        if ($id) {
            $script = Script::findPk($id);

            if (! $script)
                throw new Scalr_UI_Exception_NotFound();

            $script->checkPermission($this->user, $envId);

            if (!$script->accountId && ($this->user->getType() != Scalr_Account_User::TYPE_SCALR_ADMIN))
                throw new Scalr_Exception_InsufficientPermissions();

            if (!$script->envId && $this->request->getScope() == ScopeInterface::SCOPE_ENVIRONMENT)
                throw new Scalr_Exception_InsufficientPermissions();
        } else {
            $script = new Script();
            $script->accountId = $this->user->getAccountId() ?: NULL;
            $script->createdById = $this->user->getId();
            $script->createdByEmail = $this->user->getEmail();
            $script->envId = $envId;
            $version = 1;
        }

        //check variables in script content
        if (!$id && $checkScriptParameters && !$allowScriptParameters) {
            $scriptHasParameters = Script::hasVariables($content);
            if (!$scriptHasParameters) {
                /* @var $scriptVersion ScriptVersion */
                foreach ($script->getVersions() as $scriptVersion) {
                    if ($scriptVersion->version != $version) {
                        $scriptHasParameters = Script::hasVariables($scriptVersion->content);
                        if ($scriptHasParameters) break;
                    }
                }
            }
            if ($scriptHasParameters) {
                $this->response->data(['showScriptParametersConfirmation' => true]);
                $this->response->failure();
                return;
            }
        }

        $script->name = $name;
        $script->description = $description;
        $script->timeout = $timeout ? $timeout : NULL;
        $script->isSync = $isSync == 1 ? 1 : 0;
        $script->allowScriptParameters = $allowScriptParameters ? 1 : 0;
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
        $this->response->data(['script' => array_merge($this->getScript($script), $this->getScriptInfo($script))]);
    }


    /**
     * @param int $scriptId
     * @param string $name
     * @throws Scalr_Exception_Core
     * @throws Scalr_UI_Exception_NotFound
     */
    public function xForkAction($scriptId, $name)
    {
        $this->request->restrictAccess('SCRIPTS', 'FORK');

        if (! $name)
            throw new Scalr_Exception_Core('Name cannot be null');

        /* @var $script Script */
        $script = Script::findPk($scriptId);

        if (! $script)
            throw new Scalr_UI_Exception_NotFound();

        $script->checkPermission($this->user, $this->getEnvironmentId(true));

        $criteria = [];
        $criteria[] = ['name' => $name];
        switch ($this->request->getScope()) {
            case Script::SCOPE_ENVIRONMENT:
                $criteria[] = ['envId' => $this->getEnvironmentId(true)];
                $criteria[] = ['accountId' => $this->user->getAccountId()];
                break;
            case Script::SCOPE_ACCOUNT:
                $criteria[] = ['envId' => null];
                $criteria[] = ['accountId' => $this->user->getAccountId()];
                break;
            case Script::SCOPE_SCALR:
                $criteria[] = ['envId' => null];
                $criteria[] = ['accountId' => null];
                break;
        }

        if (Script::findOne($criteria)) {
            throw new Scalr_Exception_Core('Script name must be unique within current scope');
        }

        $forkedScript = $script->fork($name, $this->user, $this->getEnvironmentId(true));
        $this->response->success('Script successfully forked');
        $this->response->data(['script' => array_merge($this->getScript($forkedScript), $this->getScriptInfo($forkedScript))]);
    }

    /**
     * @param Script $script
     * @return array
     */
    protected function getScript($script)
    {
        $s = get_object_vars($script);
        $s['dtCreated'] = Scalr_Util_DateTime::convertTz($script->dtCreated);
        $s['dtChanged'] = Scalr_Util_DateTime::convertTz($script->dtChanged);
        $s['version'] = $script->getLatestVersion()->version;
        $s['scope'] = $script->getScope();

        return $s;
    }

    /**
     * @param string $scriptId
     * @param string $query
     * @param string $scope
     * @param JsonData $sort
     * @param int $start
     * @param int $limit
     */
    public function xListAction($scriptId = null, $query = null, $scope = null, JsonData $sort, $start = 0, $limit = 20)
    {
        $this->request->restrictAccess('SCRIPTS');

        $criteria = [];
        if ($this->user->isScalrAdmin()) {
            $criteria[] = [ 'accountId' => NULL ];
        } else {
            if ($scope == ScopeInterface::SCOPE_SCALR) {
                $criteria[] = [ 'accountId' => NULL ];

            } else if ($scope == ScopeInterface::SCOPE_ACCOUNT) {
                $criteria[] = [ 'accountId' => $this->user->getAccountId() ];
                $criteria[] = [ 'envId' => NULL ];

            } else if ($scope == ScopeInterface::SCOPE_ENVIRONMENT) {
                $criteria[] = [ 'accountId' => $this->user->getAccountId() ];
                $criteria[] = [ 'envId' => $this->getEnvironmentId(true) ];

            } else {
                $criteria[] = [ '$or' => [
                    [ 'accountId' => $this->user->getAccountId() ],
                    [ 'accountId' => NULL]
                ]];

                if ($this->request->getScope() == ScopeInterface::SCOPE_ENVIRONMENT) {
                    $criteria[] = [ '$or' => [
                        [ 'envId' => $this->getEnvironmentId(true) ],
                        [ 'envId' => NULL]
                    ]];
                } else {
                    $criteria[] = [ 'envId' => $this->getEnvironmentId(true) ];
                }
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

        if ($scriptId) {
            $criteria[] = ['id' => $scriptId];
        }

        $result = Script::find($criteria, null, Utils::convertOrder($sort, ['name' => true], ['id', 'name', 'description', 'isSync', 'dtCreated', 'dtChanged']), $limit, $start, true);
        $data = [];
        foreach ($result as $script) {
            /* @var $script Script */
            $data[] = $this->getScript($script);
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
        $this->request->restrictAccess(Acl::RESOURCE_SCRIPTS_ENVIRONMENT, Acl::PERM_SCRIPTS_ENVIRONMENT_EXECUTE);
        $data = ['scripts' => Script::getList($this->user->getAccountId(), $this->getEnvironmentId())];

        if ($shortcutId) {
            /* @var $shortcut ScriptShortcut */
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
            'farmId' => ($farmId == 0 ? '' : (string) $farmId), // TODO: remove (string) and use integer keys for whole project [UI-312]
            'farmRoleId' => (string) $farmRoleId,
            'serverId' => $serverId
        ), array('addAll', 'addAllFarm', 'requiredFarm', 'permServers', 'isScalarizedOnly'));

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
        $this->request->restrictAccess(Acl::RESOURCE_SCRIPTS_ENVIRONMENT, Acl::PERM_SCRIPTS_ENVIRONMENT_EXECUTE);

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
            $dbFarm = DBFarm::LoadByID($farmId);
            $this->user->getPermissions()->validate($dbFarm);

            $target = Script::TARGET_FARM;
            $farmId = $dbFarm->ID;
        }

        if ($farmId) {
            $this->request->checkPermissions(Entity\Farm::findPk($farmId), Acl::PERM_FARMS_SERVERS);
        }

        if ($scriptId) {
            $script = Script::findPk($scriptId);
            /* @var $script Script */
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
                /* @var $shortcut ScriptShortcut */
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
                    $servers = $this->db->GetAll("
                        SELECT server_id
                        FROM servers
                        WHERE is_scalarized = 1 AND status IN (?,?) AND farm_id=?",
                        [SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $farmId]
                    );
                    break;
                case Script::TARGET_ROLE:
                    $servers = $this->db->GetAll("
                        SELECT server_id
                        FROM servers
                        WHERE is_scalarized = 1 AND status IN (?,?) AND farm_roleid=?",
                        [SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $farmRoleId]
                    );
                    break;
                case Script::TARGET_INSTANCE:
                    $servers = $this->db->GetAll("
                        SELECT server_id
                        FROM servers
                        WHERE is_scalarized = 1 AND status IN (?,?) AND server_id=?",
                        [SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $serverId]
                    );
                    break;
                case Script::TARGET_ALL:
                    $sql = "
                        SELECT s.server_id
                        FROM servers s
                        JOIN farms f ON f.id = s.farm_id
                        WHERE s.is_scalarized = 1
                        AND s.status IN (?,?)
                        AND s.env_id = ?
                        AND " . $this->request->getFarmSqlQuery(Acl::PERM_FARMS_SERVERS);
                    $args = [ SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $this->getEnvironmentId()];
                    $servers = $this->db->GetAll($sql, $args);
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
            $cryptoTool = \Scalr::getContainer()->srzcrypto;

            // send message to start executing task (starts script)
            if (count($servers) > 0) {
                foreach ($servers as $server) {
                    $DBServer = DBServer::LoadByID($server['server_id']);

                    $msg = new Scalr_Messaging_Msg_ExecScript("Manual");
                    $msg->setServerMetaData($DBServer);

                    $script = Scalr_Scripting_Manager::prepareScript($scriptSettings, $DBServer);

                    if ($script) {
                        $DBServer->executeScript($script, $msg);

                        $this->auditLog("script.execute", $script, $DBServer);
                        
                        $manualLog = new OrchestrationLogManualScript($script['execution_id'], $msg->serverId);
                        $manualLog->userId    = $this->getUser()->getId();
                        $manualLog->userEmail = $this->getUser()->getEmail();
                        $manualLog->added     = new DateTime('now', new DateTimeZone('UTC'));
                        $manualLog->save();
                    }
                }
            }

            $this->response->success('Script execution has been queued and will occur on the selected instance(s) within a couple of minutes.');
        } else {
            $this->response->success('Script shortcut successfully saved');
        }
    }
}
