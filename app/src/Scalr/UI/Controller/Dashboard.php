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
        if ($this->user->getType() == Scalr_Account_User::TYPE_SCALR_ADMIN) {
            $this->response->page('ui/dashboard/admin.js');

        } else if ($this->user->getType() == Scalr_Account_User::TYPE_FIN_ADMIN) {
            self::loadController('Dashboard', 'Scalr_UI_Controller_Admin_Analytics')->defaultAction();

        } else {
            $loadJs = array('ui/dashboard/columns.js');
            $scope = $this->request->getScope();
            $cloudynEnabled = \Scalr::config('scalr.cloudyn.master_email') ? true : false;
            $billingEnabled = \Scalr::config('scalr.billing.enabled') ? true : false;

            $panel = $this->user->getDashboard($this->getEnvironmentId(true));

            if (empty($panel['configuration'])) {
                // default configurations
                $client = Client::Load($this->user->getAccountId());
                if ($client->GetSettingValue(CLIENT_SETTINGS::DATE_FARM_CREATED)) {
                    // old customer
                    if ($scope == 'account') {
                        $panel['configuration'] = array(
                            array(
                                array('name' => 'dashboard.announcement', 'params' => array('newsCount' => 5))
                            ),
                            array(
                                array('name' => 'dashboard.environments')
                            ),
                        );

                        if ($this->user->getType() == Scalr_Account_User::TYPE_ACCOUNT_OWNER && $billingEnabled)
                            array_unshift($panel['configuration'], array(array('name' => 'dashboard.billing')));

                    } else {
                        $panel['configuration'] = array(
                            array(
                                array('name' => 'dashboard.status'),
                                array('name' => 'dashboard.addfarm')
                            ),
                            array(
                                array('name' => 'dashboard.announcement', 'params' => array('newsCount' => 5))
                            ),
                            array(
                                array('name' => 'dashboard.lasterrors', 'params' => array('errorCount' => 10))
                            )
                        );
                    }
                } else {
                    if ($scope == 'account') {
                        // new customer
                        $panel['configuration'] = array(
                            array(
                                array('name' => 'dashboard.newuser')
                            ),
                            array(
                                array('name' => 'dashboard.announcement', 'params' => array('newsCount' => 5))
                            )
                        );
                        if ($this->user->getType() == Scalr_Account_User::TYPE_ACCOUNT_OWNER && $billingEnabled)
                            array_unshift($panel['configuration'][1], array('name' => 'dashboard.billing'));

                    } else {
                        // new customer
                        $panel['configuration'] = array(
                            array(
                                array('name' => 'dashboard.addfarm')
                            ),
                            array(
                                array('name' => 'dashboard.newuser')
                            ),
                            array(
                                array('name' => 'dashboard.announcement', 'params' => array('newsCount' => 5))
                            )
                        );
                    }
                }

                $this->user->setDashboard($this->getEnvironmentId(true), $panel);
                $panel = $this->user->getDashboard($this->getEnvironmentId(true));
            }

            // section for adding required widgets
            if ($scope == 'environment') {
                if ($cloudynEnabled &&
                    !in_array('cloudynInstalled', $panel['flags']) &&
                    !in_array('dashboard.cloudyn', $panel['widgets']) &&
                    !!$this->environment->isPlatformEnabled(SERVER_PLATFORMS::EC2))
                {
                    if (! isset($panel['configuration'][0])) {
                        $panel['configuration'][0] = array();
                    }
                    array_unshift($panel['configuration'][0], array('name' => 'dashboard.cloudyn'));
                    $panel['flags'][] = 'cloudynInstalled';
                    $this->user->setDashboard($this->getEnvironmentId(), $panel);
                    $panel = $this->user->getDashboard($this->getEnvironmentId());
                }
            }

            $panel = $this->fillDash($panel);

            $conf = $this->getContainer()->config->get('scalr.load_statistics.connections.plotter');
            $monitoringUrl = "{$conf['scheme']}://{$conf['host']}:{$conf['port']}";

            $this->response->page('ui/dashboard/view.js',
                array(
                    'panel' => $panel,
                    'flags' => array(
                        'cloudynEnabled' => $cloudynEnabled,
                        'billingEnabled' => $billingEnabled
                    ),
                    'params' => array(
                        'monitoringUrl' => $monitoringUrl
                    )
                ),
                $loadJs,
                array('ui/dashboard/view.css', 'ui/analytics/analytics.css')
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
