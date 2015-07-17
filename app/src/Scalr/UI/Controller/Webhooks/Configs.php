<?php
use Scalr\Acl\Acl;
use Scalr\Model\Entity\WebhookEndpoint;
use Scalr\Model\Entity\WebhookConfig;
use Scalr\Model\Entity\WebhookConfigEndpoint;
use Scalr\Model\Entity\WebhookConfigEvent;
use Scalr\Model\Entity\WebhookConfigFarm;
use Scalr\UI\Request\JsonData;
use Scalr\UI\Request\Validator;
use Scalr\DataType\ScopeInterface;

class Scalr_UI_Controller_Webhooks_Configs extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'webhookId';

    public function hasAccess()
    {
        return ($this->request->getScope() == WebhookConfig::SCOPE_ENVIRONMENT && $this->request->isAllowed(Acl::RESOURCE_ENVADMINISTRATION_WEBHOOKS) && !$this->user->isScalrAdmin() ||
               $this->request->getScope() == WebhookConfig::SCOPE_ACCOUNT && $this->request->isAllowed(Acl::RESOURCE_ADMINISTRATION_WEBHOOKS) && !$this->user->isScalrAdmin() ||
               $this->request->getScope() == WebhookConfig::SCOPE_SCALR && $this->user->isScalrAdmin());
    }

    /**
     * @param WebhookConfig $webhook
     * @throws Exception
     */
    private function canEditWebhook($webhook)
    {
        return $this->request->getScope() == $webhook->getScope() &&
               ($webhook->getScope() == WebhookConfig::SCOPE_SCALR ||
                $webhook->getScope() == WebhookConfig::SCOPE_ACCOUNT && $webhook->accountId == $this->user->getAccountId() ||
                $webhook->getScope() == WebhookConfig::SCOPE_ENVIRONMENT && $webhook->envId == $this->getEnvironmentId() && $webhook->accountId == $this->user->getAccountId());
    }

    public function defaultAction()
    {
        $farms = [];
        if ($this->getEnvironmentId(true) && $this->request->getScope() == WebhookConfig::SCOPE_ENVIRONMENT) {
            $farms = $this->db->getAll('SELECT id, name FROM farms WHERE env_id = ? ORDER BY name', array($this->getEnvironmentId()));
        }
        $this->response->page('ui/webhooks/configs/view.js',
            array(
                'scope' => $this->request->getScope(),
                'configs' => $this->getList(),
                'events' => $this->getEventsList(),
                'farms'  => $farms,
                'endpoints' => $this->getEndpoints()
            ),
            array('ui/webhooks/webhooks.js')
        );
    }

    /**
     * @param string $webhookId
     * @throws Exception
     */
    public function xRemoveAction($webhookId)
    {
        $webhook = WebhookConfig::findPk($webhookId);
        if (!$this->canEditWebhook($webhook)) {
            throw new Scalr_Exception_Core('Insufficient permissions to remove webhook');
        }

        $webhook->delete();
        $this->response->success("Webhook successfully removed");
    }

    /**
     * @param string $webhookId
     * @param string $name
     * @param JsonData $endpoints
     * @param JsonData $events
     * @param JsonData $farms
     * @param int $timeout
     * @param int $attempts
     * @param boolean $skipPrivateGv
     * @param string $postData
     * @throws Exception
     */
    public function xSaveAction($webhookId, $name, JsonData $endpoints, JsonData $events, JsonData $farms, $timeout = 3, $attempts = 3, $skipPrivateGv = 0, $postData = '')
    {
        if (!$webhookId) {
            $webhook = new WebhookConfig();
            $webhook->setScope($this->request->getScope(), $this->user->getAccountId(), $this->getEnvironmentId(true));
        } else {
            $webhook = WebhookConfig::findPk($webhookId);
            if (!$this->canEditWebhook($webhook)) {
                throw new Scalr_Exception_Core('Insufficient permissions to edit webhook');
            }
        }

        $validator = new Validator();
        $validator->validate($name, 'name', Validator::NOEMPTY);

        if (!$validator->isValid($this->response))
            return;


        $webhook->name = $name;
        $webhook->postData = $postData;
        $webhook->skipPrivateGv = $skipPrivateGv;
        $webhook->timeout = $timeout;
        $webhook->attempts = $attempts;
        $webhook->save();

        //save endpoints
        $endpoints = (array)$endpoints;
        foreach (WebhookConfigEndpoint::findByWebhookId($webhook->webhookId) as $webhookConfigEndpoint) {
            $index = array_search($webhookConfigEndpoint->endpointId, $endpoints);
            if ($index === false) {
                $webhookConfigEndpoint->delete();
            } else {
                unset($endpoints[$index]);
            }
        }
        if (!empty($endpoints)) {
            $criteria = [];
            $criteria[] = ['endpointId'  => ['$in' => $endpoints]];
            switch ($this->request->getScope()) {
                case WebhookConfig::SCOPE_ENVIRONMENT:
                    $criteria[] = ['$or' => [
                        ['$and' => [['accountId' => $this->user->getAccountId()], ['envId' => $this->getEnvironmentId()], ['level' => WebhookConfig::LEVEL_ENVIRONMENT]]],
                        ['$and' => [['accountId' => $this->user->getAccountId()], ['envId' => null], ['level' => WebhookConfig::LEVEL_ACCOUNT]]],
                        ['$and' => [['accountId' => null], ['envId' => null], ['level' => WebhookConfig::LEVEL_SCALR]]]
                    ]];
                    break;
                case WebhookConfig::SCOPE_ACCOUNT:
                    $criteria[] = ['$or' => [
                        ['$and' => [['accountId' => $this->user->getAccountId()], ['envId' => null], ['level' => WebhookConfig::LEVEL_ACCOUNT]]],
                        ['$and' => [['accountId' => null], ['envId' => null], ['level' => WebhookConfig::LEVEL_SCALR]]]
                    ]];
                    break;
                case WebhookConfig::SCOPE_SCALR:
                    $criteria[] = ['level' => WebhookConfig::LEVEL_SCALR];
                    $criteria[] = ['envId' => null];
                    $criteria[] = ['accountId' => null];
                    break;

            }

            foreach (WebhookEndpoint::find($criteria) as $endpoint) {
                $configEndpoint = new WebhookConfigEndpoint();
                $configEndpoint->webhookId = $webhook->webhookId;
                $configEndpoint->setEndpoint($endpoint);
                $configEndpoint->save();
            }
        }

        //save events
        $allEvents = $this->getEventsList();
        $events = (array)$events;
        foreach (WebhookConfigEvent::findByWebhookId($webhook->webhookId) as $event) {
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
        $farms = (array)$farms;
        if (empty($farms)) {
            $farms = [0];
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


        $endpoints = [];
        foreach ($webhook->getEndpoints() as $endpoint) {
            $endpoints[] = $endpoint->endpointId;
        }

        $events = [];
        foreach ($webhook->getEvents() as $event) {
            $events[] = $event->eventType;
        }
        $farms = [];
        foreach ($webhook->getFarms() as $farm) {
            if ($farm->farmId) {
                $farms[] = $farm->farmId;
            }
        }

        $this->response->success('Webhook successfully saved');

        $this->response->data(array(
            'webhook' => array(
                'webhookId'     => $webhook->webhookId,
                'name'          => $webhook->name,
                'postData'      => $webhook->postData,
                'timeout'       => $webhook->timeout,
                'attempts'      => $webhook->attempts,
                'skipPrivateGv' => $webhook->skipPrivateGv,
                'endpoints'     => $endpoints,
                'events'        => $events,
                'farms'         => $farms,
                'scope'         => $webhook->getScope()
            )
        ));
    }

    /**
     * @param   string  $scope
     */
    public function xListAction($scope = '')
    {
        $this->response->data(array('configs' => $this->getList($scope)));
    }

    /**
     * @param JsonData $webhookIds
     * @throws Exception
     */
    public function xGroupActionHandlerAction(JsonData $webhookIds)
    {
        $processed = array();
        $errors = array();
        if (!empty($webhookIds)) {
            $webhooks = WebhookConfig::find([['webhookId'  => ['$in' => $webhookIds]]]);
            foreach ($webhooks as $webhook) {
                if (!$this->canEditWebhook($webhook)) {
                    $errors[] = 'Insufficient permissions to remove webhook';
                } else {
                    $processed[] = $webhook->webhookId;
                    $webhook->delete();
                }
            }
        }
        $num = count($webhookIds);
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
        $events = EVENT_TYPE::getScriptingEventsWithScope();
        $envId = null;
        if ($this->request->getScope() == WebhookConfig::SCOPE_ENVIRONMENT) {
            $envId = (int)$this->getEnvironmentId(true);
        }

        //Temporary added new events like this, workign on events refactoring
        $events['HostInitFailed'] = [
            'name'        => 'HostInitFailed',
            'description' => 'Instance was unable to initialize',
            'scope'       => 'scalr'
        ];
        $events['InstanceLaunchFailed'] = [
            'name'        => 'InstanceLaunchFailed',
            'description' => 'Scalr failed to launch instance due to cloud error',
            'scope'       => 'scalr'
        ];

        $events = array_merge($events, \Scalr\Model\Entity\EventDefinition::getList($this->user->getAccountId(), $envId));

        return $events;

    }

    /**
     * @param   string      $scope
     * @return  array
     * @throws Scalr_Exception_Core
     */
    private function getList($scope = '')
    {
        $criteria = [];

        switch ($this->request->getScope()) {
            case WebhookConfig::SCOPE_ENVIRONMENT:
                $criteria[] = ['$or' => [
                    ['$and' => [['accountId' => $this->user->getAccountId()], ['envId' => $this->getEnvironmentId()], ['level' => WebhookConfig::LEVEL_ENVIRONMENT]]],
                    ['$and' => [['accountId' => $this->user->getAccountId()], ['envId' => null], ['level' => WebhookConfig::LEVEL_ACCOUNT]]],
                    ['$and' => [['accountId' => null], ['envId' => null], ['level' => WebhookConfig::LEVEL_SCALR]]]
                ]];
                break;
            case WebhookConfig::SCOPE_ACCOUNT:
                $criteria[] = ['$or' => [
                    ['$and' => [['accountId' => $this->user->getAccountId()], ['envId' => null], ['level' => WebhookConfig::LEVEL_ACCOUNT]]],
                    ['$and' => [['accountId' => null], ['envId' => null], ['level' => WebhookConfig::LEVEL_SCALR]]]
                ]];
                break;
            case WebhookConfig::SCOPE_SCALR:
                $criteria[] = ['level' => WebhookConfig::LEVEL_SCALR];
                $criteria[] = ['envId' => null];
                $criteria[] = ['accountId' => null];
                break;
        }

        $scopeLinking = [
            ScopeInterface::SCOPE_SCALR => WebhookConfig::LEVEL_SCALR,
            ScopeInterface::SCOPE_ACCOUNT => WebhookConfig::LEVEL_ACCOUNT,
            ScopeInterface::SCOPE_ENVIRONMENT => WebhookConfig::LEVEL_ENVIRONMENT
        ];

        if ($scope && array_key_exists($scope, $scopeLinking)) {
            $criteria[] = ['level' => $scopeLinking[$scope]];
        }

        foreach (WebhookConfig::find($criteria) as $entity) {
            $webhook = [
                'webhookId' => $entity->webhookId,
                'name'      => $entity->name,
                'scope'     => $entity->getScope()
            ];
            $endpoints = [];
            foreach ($entity->getEndpoints() as $endpoint) {
                $endpoints[] = $endpoint->endpointId;
            }

            $events = [];
            foreach ($entity->getEvents() as $event) {
                $events[] = $event->eventType;
            }

            $farms = array();
            foreach ($entity->getFarms() as $farm) {
                if ($farm->farmId) {
                    $farms[] =$farm->farmId;
                }
            }
            $webhook['postData'] = $this->request->getScope() == $entity->getScope() ? $entity->postData : '';
            $webhook['timeout'] = $entity->timeout;
            $webhook['attempts'] = $entity->attempts;
            $webhook['skipPrivateGv'] = $entity->skipPrivateGv;
            $webhook['endpoints'] = $endpoints;
            $webhook['events'] = $events;
            $webhook['farms'] = $farms;
            $list[] = $webhook;
        }

        return $list;
    }

    private function getEndpoints()
    {
        $endpoints = array();

        $criteria = [];

        switch ($this->request->getScope()) {
            case WebhookEndpoint::SCOPE_ENVIRONMENT:
                $criteria[] = ['$or' => [
                ['$and' => [['accountId' => $this->user->getAccountId()], ['envId' => $this->getEnvironmentId()], ['level' => WebhookEndpoint::LEVEL_ENVIRONMENT]]],
                ['$and' => [['accountId' => $this->user->getAccountId()], ['envId' => null], ['level' => WebhookEndpoint::LEVEL_ACCOUNT]]],
                ['$and' => [['accountId' => null], ['envId' => null], ['level' => WebhookEndpoint::LEVEL_SCALR]]]
                ]];
                break;
            case WebhookEndpoint::SCOPE_ACCOUNT:
                $criteria[] = ['$or' => [
                ['$and' => [['accountId' => $this->user->getAccountId()], ['envId' => null], ['level' => WebhookEndpoint::LEVEL_ACCOUNT]]],
                ['$and' => [['accountId' => null], ['envId' => null], ['level' => WebhookEndpoint::LEVEL_SCALR]]]
                ]];
                break;
            case WebhookEndpoint::SCOPE_SCALR:
                $criteria[] = ['level' => WebhookEndpoint::LEVEL_SCALR];
                $criteria[] = ['envId' => null];
                $criteria[] = ['accountId' => null];
                break;

        }

        foreach (WebhookEndpoint::find($criteria) as $entity) {
            $endpoints[] = [
                'id'         => $entity->endpointId,
                'url'        => $entity->url,
                'isValid'    => $entity->isValid
            ];
        }

        return $endpoints;

    }


}
