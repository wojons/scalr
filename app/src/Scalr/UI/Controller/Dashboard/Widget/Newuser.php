<?php

use Scalr\DataType\ScopeInterface;

class Scalr_UI_Controller_Dashboard_Widget_Newuser extends Scalr_UI_Controller_Dashboard_Widget
{
    public function getDefinition()
    {
        return array(
            'type' => 'local'
        );
    }

    public function getContent($params = array())
    {
        $items = [];

        $user = [
            'text' => 'Invite a Colleague',
            'href' => '#/account/users',
            'status' => $this->db->GetOne('SELECT COUNT(*) FROM account_users WHERE account_id = ?', [$this->user->getAccountId()]) > 1,
            'info' =>
                'You can add additional users to your Scalr account via the <a href="#/account/users">Users Menu</a>. ' .
                'It\'s also possible to configure different permission sets for each user. ' .
                'You can learn <a href="https://scalr-wiki.atlassian.net/wiki/x/VwDV" target="_blank">more about users on the Scalr Wiki</a>.'
        ];

        /*$team = [
            'text' => 'Create team',
            'href' => '#/account/teams',
            'status' => !!$this->db->GetOne('SELECT COUNT(*) FROM account_teams WHERE account_id = ?', [$this->user->getAccountId()]),
            'info' => ''
        ];*/

        if ($this->request->getScope() == ScopeInterface::SCOPE_ACCOUNT) {
            $items[] = [
                'title' => 'Setup steps',
                'items' => [[
                    'text' => 'Add cloud credentials',
                    'status' => !!$this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED),
                    'href' => '#/account/environments/' . $this->user->getDefaultEnvironment()->id . '/clouds',
                    'info' =>
                        'Cloud Credentials are added through the <a href="#/account/environments">Environments Menu</a>. ' .
                        'For step-by-step instructions, review the <a href="https://scalr-wiki.atlassian.net/wiki/x/yw8b" target="_blank">Quick Start guide on the Scalr Wiki</a> for your Cloud Platform of choice.'
                ], [
                    'text' => 'Start Managing Cloud Resources',
                    'status' => false, //!!$this->db->GetOne('SELECT COUNT(*) FROM governance WHERE env_id = ?', [$this->getEnvironmentId()]),
                    'href' => '#/dashboard',
                    'info' => "After you've added Cloud Credentials, navigate to your Scalr Environment, and continue following the new user checklist there."
                ]]
            ];

            $items[] = [
                'title' => 'Get your Organization on Scalr',
                'items' => [ $user ]
            ];

        } else {
            $items[] = [
                'title' => 'Setup steps',
                'items' => [[
                    'text' => 'Add cloud credentials',
                    'href' => '#/account/environments/' . $this->getEnvironmentId() . '/clouds',
                    'status' => !!count($this->getEnvironment()->getEnabledPlatforms()),
                    'info' =>
                        'Cloud Credentials are added through the <a href="#/account/environments">Environments Menu</a>. ' .
                        'For step-by-step instructions, review the <a href="https://scalr-wiki.atlassian.net/wiki/x/yw8b" target="_blank">Quick Start guide on the Scalr Wiki</a> for your Cloud Platform of choice.'
                ]]
            ];

            $items[] = [
                'title' => 'Get started with Scalr',
                'items' => [[
                    'text' => 'Try the <a href="https://scalr-wiki.atlassian.net/wiki/x/XhUb" target="_blank">Three Tier App Tutorial Series</a>'
                ], [
                    'text' => 'Create a Role',
                    'href' => '#/roles/create',
                    'status' => !!$this->db->GetOne('SELECT COUNT(*) FROM roles WHERE env_id = ?', [$this->getEnvironmentId()]),
                    'info' =>
                        'Navigate to the <a href="#/roles">Roles Library</a> to create a Role. Roles are Server templates that can be reused ' .
                        'across your infrastructure; you can learn <a href="https://scalr-wiki.atlassian.net/wiki/x/JYDq" target="_blank">more about Roles on the Scalr Wiki</a>.'
                ], [
                    'text' => 'Create a Farm',
                    'href' => '#/farms/designer',
                    'status' => !!$this->db->GetOne('SELECT COUNT(*) FROM farms WHERE env_id = ?', [$this->getEnvironmentId()]),
                    'info' =>
                        'Open the <a href="#/farms">Farms List</a> to create your first Farm. Farms are a collection of parameterized Roles ' .
                        '(named Farm Roles in this context); you can learn <a href="https://scalr-wiki.atlassian.net/wiki/x/vg8b" target="_blank">more about Farms on the Scalr Wiki</a>.'
                ], [
                    'text' => 'Create a Script',
                    'href' => '#/scripts?new=true',
                    'status' => !!$this->db->GetOne('SELECT COUNT(*) FROM scripts WHERE account_id = ?', [$this->user->getAccountId()]),
                    'info' =>
                        '<a href="#/scripts">Scripts</a> are regular shell (or Python, Perl, Ruby etc.) scripts that you add into Scalr. ' .
                        'Once you\'ve added a Script in Scalr, Scalr can transfer it to managed Servers and execute it there.'
                ], [
                    'text' => 'Create an Orchestration Rule',
                    'status' =>
                        !!$this->db->GetOne('SELECT COUNT(*) FROM account_scripts WHERE account_id = ?', [$this->user->getAccountId()]) ||
                        !!$this->db->GetOne('SELECT COUNT(*) FROM role_scripts rs JOIN roles r ON rs.role_id = r.id WHERE r.env_id = ?', [$this->getEnvironmentId()]) ||
                        !!$this->db->GetOne('SELECT COUNT(*) FROM farm_role_scripts frs JOIN farms f ON frs.farmid = f.id WHERE f.env_id = ?', [$this->getEnvironmentId()])
                    ,
                    'info' =>
                        'Orchestration Rules can be added to any Role you created, and to any Farm Role you add. ' .
                        'They let you define event-based automation (such as e.g. running a specific shell script when ' .
                        'a new instance comes online); you can learn <a href="https://scalr-wiki.atlassian.net/wiki/x/wA8b" target="_blank">more about Orchestration on the Scalr Wiki</a>.'
                ], [
                    'text' => 'Launch a Farm',
                    'href' => '#/farms',
                    'status' => !!$this->db->GetOne('SELECT COUNT(*) FROM farms WHERE env_id = ? AND dtlaunched IS NOT NULL', [$this->getEnvironmentId()]),
                    'info' =>
                        'Once you\'ve created and configured a Farm, launch it from the <a href="#/farms/view">Farms List</a>. ' .
                        'Launching a Farm results in Scalr provisioning instances from your cloud accoridng to the Farm Roles you configured. ' .
                        'Once a Farm is launched, certain configuration cannot be changed until the Farm is terminated; ' .
                        'you can learn more about <a href="https://scalr-wiki.atlassian.net/wiki/x/QoEs" target="_blank">Farm lifecycle management on the Scalr Wiki</a>.'
                ], [
                    'text' => 'Create a Global Variable',
                    'href' => '#/core/variables',
                    'status' =>
                        !!$this->db->GetOne('SELECT COUNT(*) FROM account_variables WHERE account_id = ?', [$this->user->getAccountId()]) ||
                        !!$this->db->GetOne('SELECT COUNT(*) FROM client_environment_variables WHERE env_id = ?', [$this->getEnvironmentId()]) ||
                        !!$this->db->GetOne('SELECT COUNT(*) FROM farm_variables fv JOIN farms f ON fv.farm_id = f.id WHERE f.env_id = ?', [$this->getEnvironmentId()]) ||
                        !!$this->db->GetOne('SELECT COUNT(*) FROM role_variables rv JOIN roles r ON rv.role_id = r.id WHERE r.env_id = ?', [$this->getEnvironmentId()])
                    ,
                    'info' =>
                        'Global Variables let you configure the automation you create using Scalr\'s Orchestration Engine. ' .
                        'Any Global Variable you define will be placed by Scalr in the environment when an Orchestration Rules ' .
                        'executes a script. Global Variables are very flexible, and can be defined in a number of different scopes; ' .
                        'you can learn <a href="https://scalr-wiki.atlassian.net/wiki/x/hiIb" target="_blank">more about Global Variables on the Scalr Wiki</a>.'
                ]]
            ];

            $items[] = [
                'title' => 'Get your Organization on Scalr',
                'items' => [ $user, [
                    'text' => 'Enforce a Governance Policy',
                    'href' => '#/core/governance',
                    'status' => !!$this->db->GetOne('SELECT COUNT(*) FROM governance WHERE env_id = ?', [$this->getEnvironmentId()]),
                    'info' =>
                        '<a href="#/core/governance">Governance</a> lets you create and enforce policies that control how users can consume the cloud resources available in this Environment. ' .
                        'Governance Policies include restricting instance types or networks, setting lease periods on infrastructure, and more.'
                ]]
            ];
        }

        return $items;
    }
}
