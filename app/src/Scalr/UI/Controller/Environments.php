<?php

class Scalr_UI_Controller_Environments extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'envId';

    private $checkVarError;

    public static function getApiDefinitions()
    {
        return array('xListEnvironments', 'xGetInfo', 'xCreate', 'xSave', 'xRemove');
    }

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && ($this->user->canManageAcl() || $this->user->isTeamOwner());
    }

    public function xListEnvironmentsAction()
    {
        $this->request->defineParams(array(
            'sort' => array('type' => 'json')
        ));

        $selectStmt = "SELECT e.id, e.name, e.dt_added AS dtAdded, e.status";
        if ($this->user->isAccountOwner()) {
            $sql = "
                $selectStmt
                FROM client_environments e
                WHERE e.client_id = ? AND :FILTER:
                GROUP BY e.id
            ";
            $params = array($this->user->getAccountId());
        } else {
            $sql = "
                $selectStmt
                FROM client_environments e
                JOIN account_team_envs te ON e.id = te.env_id
                JOIN account_team_users tu ON te.team_id = tu.team_id
                WHERE e.client_id = ? AND tu.user_id = ? AND :FILTER:
                GROUP BY e.id
            ";

            $params = array($this->user->getAccountId(), $this->user->id);
        }

        $response = $this->buildResponseFromSql($sql, array('id', 'name', 'dtAdded', 'status'), array(), $params);
        foreach ($response['data'] as &$row) {
            $row['platforms'] = array();
            foreach (Scalr_Environment::init()->loadById($row['id'])->getEnabledPlatforms() as $platform) {
                $row['platforms'][] = SERVER_PLATFORMS::GetName($platform);
            }

            $row['platforms'] = implode(', ', $row['platforms']);
            $row['dtAdded'] = Scalr_Util_DateTime::convertTz($row['dtAdded']);
        }

        $this->response->data($response);
    }

    public function xRemoveAction()
    {
        if (!$this->user->isAccountOwner())
            throw new Scalr_Exception_InsufficientPermissions();

        $env = Scalr_Environment::init()->loadById($this->getParam('envId'));
        $this->user->getPermissions()->validate($env);
        $env->delete();

        if ($env->id == $this->getEnvironmentId())
            Scalr_Session::getInstance()->setEnvironmentId(null); // reset

        $this->response->success("Environment has been successfully removed.");
        $this->response->data(array(
            'env' => array(
                'id' => $env->id
            ),
            'flagReload' => ($env->id == $this->getEnvironmentId() ? true : false)
        ));
    }

    public function xCreateAction()
    {
        if (!$this->user->isAccountOwner())
            throw new Scalr_Exception_InsufficientPermissions();

        if (!$this->getParam('name'))
            throw new Exception('Name cannot be blank.');

        $this->user->getAccount()->validateLimit(Scalr_Limits::ACCOUNT_ENVIRONMENTS, 1);
        $env = $this->user->getAccount()->createEnvironment($this->getParam('name'));

        $this->response->success("Environment has been successfully created.");
        $this->response->data(array(
            'env' => array(
                'id'   => $env->id,
                'name' => $env->name,
            )
        ));
    }

    protected function getEnvironmentInfo()
    {
        $env = Scalr_Environment::init();
        $env->loadById($this->getParam('envId'));
        $this->user->getPermissions()->validate($env);

        $params = array();

        $params[ENVIRONMENT_SETTINGS::TIMEZONE] = $env->getPlatformConfigValue(ENVIRONMENT_SETTINGS::TIMEZONE);

        return array(
            'id'               => $env->id,
            'name'             => $env->name,
            'params'           => $params,
            'enabledPlatforms' => $env->getEnabledPlatforms()
        );
    }

    public function xGetInfoAction()
    {
        $this->response->data(array('environment' => $this->getEnvironmentInfo()));
    }

    private function checkVar($name, $type, $env, $requiredError = '', $group = '')
    {
        $varName = str_replace('.', '_', ($group != '' ? $name . '.' . $group : $name));

        switch ($type) {
            case 'int':
                if ($this->getParam($varName)) {
                    return intval($this->getParam($varName));
                } else {
                    $value = $env->getPlatformConfigValue($name, true, $group);
                    if (!$value && $requiredError)
                        $this->checkVarError[$name] = $requiredError;

                    return $value;
                }
                break;

            case 'string':
                if ($this->getParam($varName)) {
                    return $this->getParam($varName);
                } else {
                    $value = $env->getPlatformConfigValue($name, true, $group);
                    if ($value == '' && $requiredError)
                        $this->checkVarError[$name] = $requiredError;

                    return $value;
                }
                break;

            case 'password':
                if ($this->getParam($varName) && $this->getParam($varName) != '******') {
                    return $this->getParam($varName);
                } else {
                    $value = $env->getPlatformConfigValue($name, true, $group);
                    if ($value == '' && $requiredError)
                        $this->checkVarError[$name] = $requiredError;

                    return $value;
                }
                break;

            case 'bool':
                return $this->getParam($varName) ? 1 : 0;
        }
    }

    public function xSaveAction()
    {
        $this->request->defineParams(array('envId' => array('type' => 'int')));

        $env = Scalr_Environment::init()->loadById($this->getParam('envId'));
        $this->user->getPermissions()->validate($env);

        if (!($this->user->isAccountOwner() || $this->user->isAccountAdmin()))
            throw new Scalr_Exception_InsufficientPermissions();

        $pars = array();

        // check for settings
        $pars[ENVIRONMENT_SETTINGS::TIMEZONE] = $this->checkVar(ENVIRONMENT_SETTINGS::TIMEZONE, 'string', $env, "Timezone required");

        $env->setPlatformConfig($pars);

        if (!$this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED))
            $this->user->getAccount()->setSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED, time());

        $this->response->success('Environment has been saved.');
    }
}
