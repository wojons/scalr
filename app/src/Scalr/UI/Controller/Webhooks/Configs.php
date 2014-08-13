<?php
use Scalr\Acl\Acl;
use Scalr\Model\Entity\WebhookEndpoint;
use Scalr\Model\Entity\WebhookConfig;
use Scalr\Model\Entity\WebhookConfigEndpoint;
use Scalr\Model\Entity\WebhookConfigEvent;
use Scalr\Model\Entity\WebhookConfigFarm;

class Scalr_UI_Controller_Webhooks_Configs extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'webhookId';

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/webhooks/configs/view.js',
            array(
                'events' => $this->getEventsList(),
                'farms'  => $this->db->getAll('SELECT id, name FROM farms WHERE env_id = ? ORDER BY name', array($this->getEnvironmentId()))
            ),
            array('ui/webhooks/dataconfig.js', 'ux-boxselect.js',),
            array(),
            array('webhooks.endpoints', 'webhooks.configs')
        );
    }

    public function xRemoveAction()
    {
        $webhook = WebhookConfig::findPk($this->request->getParam('webhookId'));
        if ($webhook->envId != $this->getEnvironmentId() || $webhook->accountId != $this->getEnvironment()->clientId) {
            throw new Scalr_Exception_Core('Insufficient permissions to remove webhook');
        }

        $webhook->delete();
        $this->response->success("Webhook successfully removed");
    }

    public function xSaveAction()
    {
        $this->request->defineParams(array(
            'endpoints' => array('type' => 'json'), 'action',
            'events'    => array('type' => 'json'), 'action',
            'farms'    => array('type' => 'json'), 'action'
        ));

        if (!$this->request->getParam('webhookId')) {
            $webhook = new WebhookConfig();
            $webhook->level = WebhookConfig::LEVEL_ENVIRONMENT;
            $webhook->accountId = $this->getEnvironment()->clientId;
            $webhook->envId = $this->getEnvironmentId();
        } else {
            $webhook = WebhookConfig::findPk($this->request->getParam('webhookId'));
            if ($webhook->envId != $this->getEnvironmentId() || $webhook->accountId != $this->getEnvironment()->clientId) {
                throw new Scalr_Exception_Core('Insufficient permissions to edit webhook');
            }
        }

        $webhook->name = $this->request->getParam('name');
        $webhook->postData = $this->request->getParam('postData');
        $webhook->skipPrivateGv = $this->request->getParam('skipPrivateGv') == 'on' ? 1 : 0;

        $webhook->save();

        //save endpoints
        $endpoints = $this->getParam('endpoints');
        foreach(WebhookConfigEndpoint::findByWebhookId($webhook->webhookId) as $endpoint) {
            $index = array_search($endpoint->endpointId, $endpoints);
            if ($index === false) {
                $endpoint->delete();
            } else {
                unset($endpoints[$index]);
            }
        }
        if (!empty($endpoints)) {
            $endpoints = WebhookEndpoint::find(array(
                array('accountId' => $this->getEnvironment()->clientId),
                array('envId'     => $this->getEnvironmentId()),
                array(
                    'endpointId'  => array('$in' => $endpoints)
                )
            ));
            foreach($endpoints as $endpoint) {
                $configEndpoint = new WebhookConfigEndpoint();
                $configEndpoint->webhookId = $webhook->webhookId;
                $configEndpoint->setEndpoint($endpoint);
                $configEndpoint->save();
            }
        }

        //save events
        $events = $this->getParam('events');
        $allEvents = $this->getEventsList();
        foreach(WebhookConfigEvent::findByWebhookId($webhook->webhookId) as $event) {
            $index = array_search($event->eventType, $events);
            if ($index === false) {
                if (isset($allEvents[$event->eventType])) {//20486-rebundlecomplete-emails - we shouldn't remove some events(RebundleComplete...)
                    $event->delete();
                }
            } else {
                unset($events[$index]);
            }
        }
        foreach($events as $event) {
            /*if (!isset(EVENT_TYPE::getScriptingEvents()[$event])) {
                continue;
            }*/
            $configEvent = new WebhookConfigEvent();
            $configEvent->webhookId = $webhook->webhookId;
            $configEvent->eventType = $event;
            $configEvent->save();
        }

        //save farms
        $farms = $this->getParam('farms');
        if (empty($farms)) {
            $farms = array(0);
        }
        foreach(WebhookConfigFarm::findByWebhookId($webhook->webhookId) as $farm) {
            $index = array_search($farm->farmId, $farms);
            if ($index === false) {
                $farm->delete();
            } else {
                unset($farms[$index]);
            }
        }

        foreach($farms as $farmId) {
            $configFarm = new WebhookConfigFarm();
            $configFarm->webhookId = $webhook->webhookId;
            $configFarm->farmId = $farmId;
            $configFarm->save();
        }


        $endpoints = array();
        foreach ($webhook->getEndpoints() as $endpoint) {
            $endpoints[] = $endpoint->endpointId;
        }

        $events = array();
        foreach ($webhook->getEvents() as $event) {
            $events[] = $event->eventType;
        }
        $farms = array();
        foreach ($webhook->getFarms() as $farm) {
            if ($farm->farmId) {
                $farms[] =$farm->farmId;
            }
        }
        $this->response->data(array(
            'webhook' => array(
                'webhookId' => $webhook->webhookId,
                'name'      => $webhook->name,
                'postData'  => $webhook->postData,
                'skipPrivateGv' => $webhook->skipPrivateGv,
                'endpoints' => $endpoints,
                'events'    => $events,
                'farms'    => $farms
            )
        ));
    }

    public function xGroupActionHandlerAction()
    {
        $this->request->defineParams(array(
            'webhookIds' => array('type' => 'json'), 'action'
        ));

        $processed = array();
        $errors = array();

        $webhooks = WebhookConfig::find(array(
            array('accountId' => $this->getEnvironment()->clientId),
            array('envId'     => $this->getEnvironmentId()),
            array(
                'webhookId'  => array('$in' => $this->getParam('webhookIds'))
            )
        ));
        foreach($webhooks as $webhook) {
            $processed[] = $webhook->webhookId;
            $webhook->delete();
        }

        $num = count($this->getParam('webhookIds'));
        if (count($processed) == $num) {
            $this->response->success('Webhooks successfully processed');
        } else {
            array_walk($errors, function(&$item) { $item = '- ' . $item; });
            $this->response->warning(sprintf("Successfully processed only %d from %d webhooks. \nFollowing errors occurred:\n%s", count($processed), $num, join($errors, '')));
        }

        $this->response->data(array('processed' => $processed));
    }

    private function getEventsList()
    {
        $events = EVENT_TYPE::getScriptingEvents();
        $envId = $this->getEnvironmentId();

        //Temporary added new events like this, workign on events refactoring

        $events['HostInitFailed'] = 'Instance was unable to initialize';
        $events['InstanceLaunchFailed'] = 'Scalr failed to launch instance due to cloud error';

        if ($envId) {
            $userEvents = $this->db->Execute("SELECT * FROM event_definitions WHERE env_id = ?", array($envId));
            while ($event = $userEvents->FetchRow()) {
                $events[$event['name']] = $event['description'];
            }
        }
        return $events;

    }

}
