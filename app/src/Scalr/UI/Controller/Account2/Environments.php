<?php
use Scalr\Acl\Acl;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\AccountCostCenterEntity;

class Scalr_UI_Controller_Account2_Environments extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'envId';

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $ccs = false;
        $unassignedCcs = false;
        if ($this->getContainer()->analytics->enabled) {
            $ccs = [];
            foreach (AccountCostCenterEntity::findByAccountId($this->user->getAccountId()) as $accountCc) {
                /* @var $accountCs \Scalr\Stats\CostAnalytics\Entity\AccountCostCenterEntity */
                $ccEntity = CostCentreEntity::findPk($accountCc->ccId);
                /* @var $ccEntity \Scalr\Stats\CostAnalytics\Entity\CostCentreEntity */
                if ($ccEntity->archived) {
                    continue;
                }
                $ccs[$accountCc->ccId] = get_object_vars($ccEntity);
            }

            $unassignedCcs = [];
            foreach ($this->user->getEnvironments() as $row) {
                $env = Scalr_Environment::init()->loadById($row['id']);
                $ccEntity = CostCentreEntity::findPk($env->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID));
                /* @var $ccEntity \Scalr\Stats\CostAnalytics\Entity\CostCentreEntity */
                if ($ccEntity && !isset($ccs[$ccEntity->ccId])) {
                    $unassignedCcs[$row['id']] = ['ccId' => $ccEntity->ccId, 'name' => $ccEntity->name];
                }
            }
        }

        $this->response->page('ui/account2/environments/view.js',
            array('ccs' => array_values($ccs), 'unassignedCcs' => $unassignedCcs),
            array('ui/account2/dataconfig.js'),
            array('ui/account2/environments/view.css'),
            array('account.environments', 'account.teams')
        );
    }

    public function xRemoveAction()
    {
        if (!$this->user->isAccountOwner() && !$this->user->isAccountSuperAdmin()) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $env = Scalr_Environment::init()->loadById($this->getParam('envId'));
        $this->user->getPermissions()->validate($env);
        $env->delete();

        if ($env->id == $this->getEnvironmentId()) {
            Scalr_Session::getInstance()->setEnvironmentId(null); // reset
        }

        $this->response->success("Environment successfully removed");
        $this->response->data(array('env' => array('id' => $env->id), 'flagReload' => $env->id == $this->getEnvironmentId() ? true : false));
    }

    public function xCloneAction()
    {
        if (!$this->user->isAccountSuperAdmin() && !$this->request->isAllowed(Acl::RESOURCE_ENVADMINISTRATION_ENV_CLOUDS)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }
        $params = array(
            'envId' => array('type' => 'int'),
            'name' => array('type' => 'string', 'validator' => array(
                Scalr_Validator::REQUIRED => true,
                Scalr_Validator::NOHTML => true
            ))
        );
        $this->request->defineParams($params);
        $this->request->validate();

        $oldEnv = Scalr_Environment::init()->loadById($this->getParam('envId'));
        $this->user->getPermissions()->validate($oldEnv);

        if ($this->request->isValid()) {
            if (!$this->user->isAccountOwner() && !$this->user->isAccountSuperAdmin())
                throw new Scalr_Exception_InsufficientPermissions();

            $this->user->getAccount()->validateLimit(Scalr_Limits::ACCOUNT_ENVIRONMENTS, 1);
            $env = $this->user->getAccount()->createEnvironment($this->getParam('name'));
            $env->status = Scalr_Environment::STATUS_ACTIVE;

            //Copy cloud credentials
            $cloudConfig = $oldEnv->getFullConfiguration();
            foreach ($cloudConfig as $group => $props)
                $env->setPlatformConfig($props, null, $group);

            //Copy teams & ACLs
            $teams = $oldEnv->getTeams();
            foreach ($teams as $teamId)
                $env->addTeam($teamId);

            //Copy Env level global variables
            $oldGv = new Scalr_Scripting_GlobalVariables($oldEnv->clientId, $oldEnv->id, Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT);
            $variables = $oldGv->getValues();

            $newGv = new Scalr_Scripting_GlobalVariables($env->clientId, $env->id, Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT);
            $newGv->setValues($variables);

            //Copy governance rules
            $oldGov = new Scalr_Governance($oldEnv->id);
            $govRules = $oldGov->getValues();

            $newGov = new Scalr_Governance($env->id);

            foreach ($govRules as $category => $rules)
                foreach ($rules as $name => $data)
                    $newGov->setValue($category, $name, $data);

            $this->response->success("Environment successfully cloned");

                $this->response->data(array(
                    'env' => array(
                        'id' => $env->id,
                        'name' => $env->name,
                        'status' => $env->status,
                        'platforms' => $env->getEnabledPlatforms(),
                        'teams' => $teams,
                        'ccId' => $env->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID)
                    )
                ));

        } else {
            $this->response->failure($this->request->getValidationErrorsMessage());
        }
    }

    public function xSaveAction()
    {
        if (!$this->user->isAccountSuperAdmin() && !$this->request->isAllowed(Acl::RESOURCE_ENVADMINISTRATION_ENV_CLOUDS)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $params = array(
            'envId' => array('type' => 'int'),
            'teams' => array('type' => 'json')
        );
        if ($this->user->isAccountOwner() || $this->user->isAccountSuperAdmin()) {
            $params['name'] = array('type' => 'string', 'validator' => array(
                Scalr_Validator::REQUIRED => true,
                Scalr_Validator::NOHTML => true
            ));
        }
        $this->request->defineParams($params);
        $this->request->validate();

        if ($this->getContainer()->analytics->enabled && $this->request->isInterfaceBetaOrNotHostedScalr()) {
            if ($this->getParam('ccId')) {
                if (!$this->getContainer()->analytics->ccs->get($this->getParam('ccId'))) {
                    $this->request->addValidationErrors('ccId', 'Invalid cost center ID');
                }
            } else {
                $this->request->addValidationErrors('ccId', 'Cost center is required field');
            }
        }

        if ($this->request->isValid()) {
            $isNew = false;
            if (!$this->getParam('envId')) {//create new environment
                if (!$this->user->isAccountOwner() && !$this->user->isAccountSuperAdmin()) {
                    throw new Scalr_Exception_InsufficientPermissions();
                }

                $this->user->getAccount()->validateLimit(Scalr_Limits::ACCOUNT_ENVIRONMENTS, 1);
                $env = $this->user->getAccount()->createEnvironment($this->getParam('name'));
                $isNew = true;
            } else {
                $env = Scalr_Environment::init()->loadById($this->getParam('envId'));
            }

            $this->user->getPermissions()->validate($env);

            if (!$this->user->isAccountSuperAdmin() && !$this->user->getAclRolesByEnvironment($env->id)->isAllowed(Acl::RESOURCE_ENVADMINISTRATION_ENV_CLOUDS))
                throw new Scalr_Exception_InsufficientPermissions();

            //set name and status
            if ($this->user->isAccountOwner() || $this->user->isAccountSuperAdmin()) {
                $env->name = $this->getParam('name');
            }

            if ($this->user->canManageAcl()) {
                $env->status = $this->getParam('status') == Scalr_Environment::STATUS_ACTIVE ? Scalr_Environment::STATUS_ACTIVE : Scalr_Environment::STATUS_INACTIVE;
            }

            $env->save();

            if ($this->user->canManageAcl()) {
                if ($this->getContainer()->analytics->enabled && $this->getParam('ccId')) {
                    $oldCcId = $env->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID);
                    $env->setPlatformConfig(array(Scalr_Environment::SETTING_CC_ID => $this->getParam('ccId')));

                    if ($isNew || $oldCcId != $this->getParam('ccId')) {
                        $cc = CostCentreEntity::findPk($this->getParam('ccId'));
                        $email = $cc->getProperty(CostCentrePropertyEntity::NAME_LEAD_EMAIL);
                        $emailData = [
                            'envName' => $env->name,
                            'ccName'  => $cc->name
                        ];

                        if (!empty($email)) {
                            \Scalr::getContainer()->mailer->sendTemplate(SCALR_TEMPLATES_PATH . '/emails/analytics_on_cc_add.eml.php', $emailData, $email);
                        }
                    }

                    if ($isNew || empty($oldCcId)) {
                        $this->getContainer()->analytics->events->fireAssignCostCenterEvent($env, $this->getParam('ccId'));
                    } elseif ($oldCcId != $this->getParam('ccId')) {
                        $this->getContainer()->analytics->events->fireReplaceCostCenterEvent($env, $this->getParam('ccId'), $oldCcId);
                    }
                }

                //set teams
                $env->clearTeams();
                if ($this->getContainer()->config->get('scalr.auth_mode') == 'ldap') {
                    foreach ($this->getParam('teams') as $name) {
                        $name = trim($name);
                        if ($name) {
                            $id = $this->db->GetOne('SELECT id FROM account_teams WHERE name = ? AND account_id = ? LIMIT 1', array($name, $this->user->getAccountId()));
                            if (! $id) {
                                $team = new Scalr_Account_Team();
                                $team->name = $name;
                                $team->accountId = $this->user->getAccountId();
                                $team->save();
                                $id = $team->id;
                            }

                            $env->addTeam($id);
                        }
                    }
                    // remove unused teams
                    $ids = $this->db->GetAll('
                        SELECT account_teams.id
                        FROM account_teams
                        LEFT JOIN account_team_envs ON account_team_envs.team_id = account_teams.id
                        WHERE ISNULL(account_team_envs.env_id) AND account_teams.account_id = ?
                    ', array($this->user->getAccountId()));

                    foreach ($ids as $id) {
                        $team = new Scalr_Account_Team();
                        $team->loadById($id['id']);
                        $team->delete();
                    }

                    if ($this->getContainer()->config->get('scalr.connections.ldap.user')) {
                        $user = strtok($this->user->getEmail(), '@');
                        $ldap = $this->getContainer()->ldap($user, null);
                        if ($ldap->isValidUsername()) {
                            $this->user->applyLdapGroups($ldap->getUserGroups());
                        }
                    }
                } else {
                    foreach ($this->getParam('teams') as $id)
                        $env->addTeam($id);
                }
            }

            $this->response->success($isNew ? 'Environment successfully created' : 'Environment saved');

            $env = Scalr_Environment::init()->loadById($env->id);//reload env to be sure we have actual params

            $teams = array();
            foreach ($env->getTeams() as $teamId) {
                if ($this->getContainer()->config->get('scalr.auth_mode') == 'ldap') {
                    $team = new Scalr_Account_Team();
                    $team->loadById($teamId);
                    $teams[] = $team->name;
                } else {
                    $teams[] = $teamId;
                }
            }

            $this->response->data(array(
                'env' => array(
                    'id' => $env->id,
                    'name' => $env->name,
                    'status' => $env->status,
                    'platforms' => $env->getEnabledPlatforms(),
                    'teams' => $teams,
                    'ccId' => $env->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID)
                )
            ));

        } else {
            $this->response->failure($this->request->getValidationErrorsMessage());
        }
    }
}
