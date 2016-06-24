<?php

use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Dashboard extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return true;
    }

    public function defaultAction()
    {
        $userType = $this->user->getType();
        if ($userType == Scalr_Account_User::TYPE_FIN_ADMIN) {
            self::loadController('Dashboard', 'Scalr_UI_Controller_Admin_Analytics')->defaultAction();

        } else {
            $js    = ['ui/dashboard/columns.js'];
            $css   = ['ui/dashboard/view.css'];
            $envId = $this->getEnvironmentId(true);
            $panel = $this->user->getDashboard($envId);
            $scope = $this->request->getScope();
            $isNewCustomer = false;

            //flags, parameters, additional stylesheets, etc
            if ($scope === 'scalr') {
                $flags  = [];
                $params = [];

            } else {
                $client = Client::Load($this->user->getAccountId());
                $isNewCustomer = !$client->GetSettingValue(CLIENT_SETTINGS::DATE_FARM_CREATED);

                $cloudynEnabled = \Scalr::config('scalr.cloudyn.master_email') ? true : false;
                $billingEnabled = \Scalr::config('scalr.billing.enabled') ? true : false;

                $plotter = $this->getContainer()->config->get('scalr.load_statistics.connections.plotter');
                $monitoringUrl = $plotter['scheme'] . '://' . $plotter['host'] . ':' . $plotter['port'];

                $css[]  = 'ui/analytics/analytics.css';

                $flags  = [
                    'cloudynEnabled' => $cloudynEnabled,
                    'billingEnabled' => $billingEnabled
                ];
                $params = ['monitoringUrl' => $monitoringUrl];
            }

            if (empty($panel['configuration'])) {
                if ($scope === 'scalr') {
                    $panel['configuration'] = [
                        [['name' => 'dashboard.scalrhealth']],
                        [['name' => 'dashboard.gettingstarted']]
                    ];

                } elseif ($isNewCustomer) {
                    if ($scope === 'account') {
                        $panel['configuration'] = [
                            [['name' => 'dashboard.newuser']],
                            [['name' => 'dashboard.announcement', 'params' => ['newsCount' => 8]]]
                        ];
                        if ($userType == Scalr_Account_User::TYPE_ACCOUNT_OWNER && $billingEnabled) {
                            array_unshift($panel['configuration'][1], ['name' => 'dashboard.billing']);
                        }

                    } else {
                        $panel['configuration'] = [
                            [['name' => 'dashboard.addfarm']],
                            [['name' => 'dashboard.newuser']],
                            [['name' => 'dashboard.announcement', 'params' => ['newsCount' => 8]]]
                        ];
                    }

                } else {
                    if ($scope === 'account') {
                        $panel['configuration'] = [
                            [['name' => 'dashboard.announcement', 'params' => ['newsCount' => 8]]],
                            [['name' => 'dashboard.environments']]
                        ];

                        if ($userType == Scalr_Account_User::TYPE_ACCOUNT_OWNER && $billingEnabled) {
                            array_unshift($panel['configuration'], [['name' => 'dashboard.billing']]);
                        }

                    } else {
                        $panel['configuration'] = [
                            [
                                ['name' => 'dashboard.status'],
                                ['name' => 'dashboard.addfarm']
                            ],
                            [['name' => 'dashboard.announcement', 'params' => ['newsCount' => 8]]],
                            [['name' => 'dashboard.lasterrors', 'params' => ['errorCount' => 10]]]
                        ];
                    }
                }

                $this->user->setDashboard($envId, $panel);
                $panel = $this->user->getDashboard($envId);
            }

            //required widgets
            $panelChanged = false;
            if ($scope === 'scalr') {
                if (!in_array('dashboard.scalrhealth', $panel['widgets'])) {
                    if (!isset($panel['configuration'][0])) {
                        $panel['configuration'][0] = [];
                    }
                    array_unshift($panel['configuration'][0], ['name' => 'dashboard.scalrhealth']);

                    $panelChanged = true;
                }

            } elseif ($scope === 'environment') {
                if ($cloudynEnabled &&
                    !in_array('cloudynInstalled', $panel['flags']) &&
                    !in_array('dashboard.cloudyn', $panel['widgets']) &&
                    !!$this->environment->isPlatformEnabled(SERVER_PLATFORMS::EC2))
                {
                    if (!isset($panel['configuration'][0])) {
                        $panel['configuration'][0] = [];
                    }
                    array_unshift($panel['configuration'][0], ['name' => 'dashboard.cloudyn']);
                    $panel['flags'][] = 'cloudynInstalled';

                    $panelChanged = true;
                }
            }

            if ($panelChanged) {
                $this->user->setDashboard($envId, $panel);
                $panel = $this->user->getDashboard($envId);
            }

            $panel = $this->fillDash($panel);

            $this->response->page('ui/dashboard/view.js',
                [
                    'panel' => $panel,
                    'flags' => $flags,
                    'params' => $params
                ],
                $js,
                $css
            );
        }
    }

    public function fillDash($panel)
    {
        $loadJs = [];
        foreach ($panel['configuration'] as &$column) {
            foreach ($column as &$wid) {
                $tt = microtime(true);

                $name = str_replace('dashboard.', '', $wid['name']);
                try {
                    $widget = Scalr_UI_Controller::loadController($name, 'Scalr_UI_Controller_Dashboard_Widget');
                } catch (Scalr_Exception_InsufficientPermissions $e) {
                    $wid = null;
                    continue;
                } catch (Exception $e) {
                    continue;
                }

                $info = $widget->getDefinition();

                if (!empty($info['js'])) {
                    $loadJs[] = $info['js'];
                }

                $wid['params'] = isset($wid['params']) && is_array($wid['params']) ? $wid['params'] : [];

                try {
                    $widget->hasWidgetAccess($wid['params']);
                } catch (Exception $e) {
                    // temp solution, need to refactor
                    $wid['params']['widgetError'] = $e->getMessage();
                    continue;
                }

                if ($info['type'] == 'local') {
                    try {
                        $wid['widgetContent'] = $widget->getContent($wid['params']);
                    } catch (ADODB_Exception $e) {
                        \Scalr::logException($e);
                        $wid['widgetError'] = 'Database error';
                    } catch (Exception $e) {
                        $wid['widgetError'] = $e->getMessage();
                    }
                    $wid['time'] = microtime(true) - $tt;
                }
            }
        }
        return $panel;
    }

    public function xSavePanelAction()
    {
        $t = microtime(true);
        $this->request->defineParams(array(
           'panel' => array('type' => 'json')
        ));

        $this->user->setDashboard($this->getEnvironmentId(true), $this->getParam('panel'));
        $panel = $this->user->getDashboard($this->getEnvironmentId(true));

        $t2 = microtime(true);
        $panel = $this->fillDash($panel);
        $t3 = microtime(true);

        $this->response->data(array(
            'panel' => $panel,
            't' => microtime(true) - $t,
            't2' => microtime(true) - $t2,
            't3' => microtime(true) - $t3,
        ));
    }

    /**
     * @param   JsonData    $widget
     * @throws  Scalr_Exception_Core
     */
    public function xUpdatePanelAction(JsonData $widget)
    {
        $panel = $this->user->getDashboard($this->getEnvironmentId(true));

        if (!empty($widget['name'])) {
            // check if a such widget's configuration has already existed in dashboard
            $existed = false;
            foreach ($panel['configuration'] as $column) {
                foreach ($column as $wid) {
                    if ($wid['name'] == $widget['name']) {
                        if (!empty($widget['params']) || !empty($wid['params'])) {
                            $a = $widget['params'];
                            sort($a);
                            $b = $wid['params'];
                            sort($b);
                            $existed = $existed || (json_encode($a) === json_encode($b));
                        } else {
                            $existed = true;
                        }
                    }
                }
            }

            if (!$existed) {
                $this->user->addDashboardWidget($this->getEnvironmentId(true), (array) $widget);
            }
        }

        $panel = $this->user->getDashboard($this->getEnvironmentId(true));
        $panel = $this->fillDash($panel);

        $this->response->success('New widget successfully added to dashboard');
        $this->response->data(array('panel' => $panel));
    }


    public function checkLifeCycle($widgets)
    {
        $result = array();

        foreach ($widgets as $id => $object) {
            $name = str_replace('dashboard.', '', $object['name']);

            try {
                $widget = Scalr_UI_Controller::loadController($name, 'Scalr_UI_Controller_Dashboard_Widget');
            } catch (Exception $e) {
                continue;
            }

            try {
                $result[$id]['widgetContent'] = $widget->getContent($object['params']);
            } catch (ADODB_Exception $e) {
                \Scalr::logException($e);
                $result[$id]['widgetError'] = 'Database error';
            } catch (Exception $e) {
                $result[$id]['widgetError'] = $e->getMessage();
            }
        }

        return $result;
    }

    public function xAutoUpdateDashAction()
    {
        $this->request->defineParams(array(
            'updateDashboard' => array('type' => 'json')
        ));
        $response = array(
            'updateDashboard' => ''
        );
        $widgets = $this->getParam('updateDashboard');
        if ($this->user) {
            if ($widgets && !empty($widgets)) {
                $response['updateDashboard'] = $this->checkLifeCycle($widgets);
            }
        }
        $this->response->data($response);
    }
}
