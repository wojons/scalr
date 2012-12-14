<?php
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
		} else {
			$loadJs = array('ui/dashboard/columns.js');
			$cloudynEnabled = CONFIG::$CLOUDYN_MASTER_EMAIL ? true : false;

			$panel = $this->user->getDashboard($this->getEnvironmentId());

			if (empty($panel['configuration'])) {
				// default configurations
				$client = Client::Load($this->user->getAccountId());
				if ($client->GetSettingValue(CLIENT_SETTINGS::DATE_FARM_CREATED)) {
					// old customer
					$panel['configuration'] = array(
						array(
							array('name' => 'dashboard.status')
						),
						array(
							array('name' => 'dashboard.announcement', 'params' => array('newsCount' => 5)),
							array('name' => 'dashboard.usagelaststat', 'params' => array('farmCount' => 5))
						),
						array(
							array('name' => 'dashboard.lasterrors', 'params' => array('errorCount' => 10)),
							array('name' => 'dashboard.uservoice', 'params' => array('sugCount' => 5))
						)
					);

				} else {
					// new customer
					$panel['configuration'] = array(
						array(
							array('name' => 'dashboard.tutorapp')
						),
						array(
							array('name' => 'dashboard.tutordns')
						),
						array(
							array('name' => 'dashboard.tutorfarm'),
							array('name' => 'dashboard.announcement', 'params' => array('newsCount' => 5))
						)
					);

				}

				$this->user->setDashboard($this->getEnvironmentId(), $panel);
				$panel = $this->user->getDashboard($this->getEnvironmentId());
			}

			// section for adding required widgets
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

			$panel = $this->fillDash($panel);

            $this->response->page('ui/dashboard/view.js',
	            array(
		            'panel' => $panel,
		            'flags' => array(
			            'cloudynEnabled' => $cloudynEnabled
		            )
	            ),
	            $loadJs,
	            array('ui/dashboard/view.css')
            );
        }
	}

	public function fillDash($panel)
	{
		foreach ($panel['configuration'] as &$column) {
			foreach ($column as &$wid) {
				$tt = microtime(true);

				$name = str_replace('dashboard.', '', $wid['name']);
				try {
					$widget = Scalr_UI_Controller::loadController($name, 'Scalr_UI_Controller_Dashboard_Widget');
				} catch (Exception $e) {
					continue;
				}

				$info = $widget->getDefinition();

				if ($info['js'])
					$loadJs[] = $info['js'];

				if ($info['type'] == 'local') {
					$wid['widgetContent'] = $widget->getContent($wid['params']);
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

	    $this->user->setDashboard($this->getEnvironmentId(), $this->getParam('panel'));
	    $panel = $this->user->getDashboard($this->getEnvironmentId());

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

	public function xUpdatePanelAction ()
	{
		$this->request->defineParams(array(
			'widget' => array('type' => 'json')
		));

		$panel = $this->user->getDashboard($this->getEnvironmentId());

		if (! is_array($panel))
			$panel = array();
		if (!strpos(json_encode($panel), json_encode($this->getParam('widget')))) {
			if (is_array($panel[0]) && is_array($panel[0]['widgets'])) {
				$panel[0]['widgets'][count($panel[0]['widgets'])] = $this->getParam('widget');
			} else {
				$panel[0]['widgets'][0] = $this->getParam('widget');
			}
		}
		$this->user->setDashboard($this->getEnvironmentId(), $panel);

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

			$result[$id] = $widget->getContent($object['params']);
		}

		return $result;
	}

	public function xAutoUpdateDashAction () {
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

	public function widgetAccountInfoAction()
	{
		require_once(APPPATH."/src/externals/chargify-client/class.ChargifyConnector.php");
		require_once(APPPATH."/src/externals/chargify-client/class.ChargifyCreditCard.php");
		require_once(APPPATH."/src/externals/chargify-client/class.ChargifyCustomer.php");
		require_once(APPPATH."/src/externals/chargify-client/class.ChargifyProduct.php");
		require_once(APPPATH."/src/externals/chargify-client/class.ChargifySubscription.php");

		$js_module = array();

		$clientId = $this->user->getAccountId();
		if ($clientId == 0) {
			array_push($js_module, array(
				'xtype' => 'displayfield',
				'fieldLabel' => 'Logged in as',
				'value' => 'SCALR ADMIN'
			));
		}
		else {
			$client = Client::Load($clientId);

			array_push($js_module, array(
				'xtype' => 'displayfield',
				'fieldLabel' => 'Logged in as',
				'value' => $client->Email
			));

			if (!$client->GetSettingValue(CLIENT_SETTINGS::BILLING_CGF_SID))
			{
				array_push($js_module, array(
					'xtype' => 'displayfield',
					'fieldLabel' => 'Plan',
					'value' => 'Development'
				));
			}
			else
			{
				$c = new ChargifyConnector();

				try
				{
					$subs = $c->getCustomerSubscription($client->GetSettingValue(CLIENT_SETTINGS::BILLING_CGF_SID));

					$color = (ucfirst($subs->getState()) != 'Active') ? 'red' : 'green';
					array_push($js_module, array(
						'xtype' => 'displayfield',
						'fieldLabel' => 'Status',
						'value' => "<span style='color:{$color}'>".ucfirst($subs->getState())."</span>"
					));

					array_push($js_module, array(
						'xtype' => 'displayfield',
						'fieldLabel' => 'Billing type',
						'value' => ucfirst($subs->getCreditCard()->getCardType()) . " (" . $subs->getCreditCard()->getMaskedCardNumber() . ")"
					));

					array_push($js_module, array(
						'xtype' => 'displayfield',
						'fieldLabel' => 'Plan',
						'value' => ucfirst($subs->getProduct()->getHandle())
					));

					array_push($js_module, array(
						'xtype' => 'displayfield',
						'fieldLabel' => 'Due date',
						'value' => date("M j Y", strtotime($subs->next_assessment_at))
					));

					array_push($js_module, array(
						'xtype' => 'displayfield',
						'fieldLabel' => 'Balance',
						'value' => "$".number_format($subs->getBalanceInCents()/100, 2)
					));
				}
				catch(Exception $e) {
					array_push($js_module, array(
						'xtype' => 'displayfield',
						'hideLabel' => true,
						'value' => "<span style='color:red;'>Billing information is not available at the moment</span>"
					));
				}
			}
		}

		$this->response->data(array(
			'module' => $js_module
		));
	}
}
