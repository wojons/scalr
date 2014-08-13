<?php
use Scalr\Acl\Acl;
use Scalr\Model\Entity\WebhookEndpoint;
use Scalr\Model\Entity\WebhookConfig;

class Scalr_UI_Controller_Webhooks extends Scalr_UI_Controller
{

    public function hasAccess()
    {
        return $this->request->isAllowed(Acl::RESOURCE_GENERAL_WEBHOOKS);
    }

    public function xGetDataAction()
    {
        $this->request->defineParams(array(
            'stores' => array('type' => 'json'), 'action'
        ));

        $stores = array();
        foreach ($this->getParam('stores') as $storeName) {
            $method = 'get' . implode('', array_map('ucfirst', explode('.', strtolower($storeName)))) . 'List';
            if (method_exists($this, $method)) {
                $stores[$storeName] = $this->$method();
            }
        }

        $this->response->data(array(
            'stores' => $stores
        ));
    }

    public function getWebhooksEndpointsList()
    {
        $list = array();
        foreach (WebhookEndpoint::findByEnvId($this->getEnvironmentId()) as $entity) {
            $list[] = array(
                'endpointId'      => $entity->endpointId,
                'url'             => $entity->url,
                'isValid'         => $entity->isValid,
                'validationToken' => $entity->validationToken,
                'securityKey'     => $entity->securityKey
            );
        }

        return $list;
    }

    public function getWebhooksConfigsList()
    {

        $list = array();
        foreach (WebhookConfig::findByEnvId($this->getEnvironmentId()) as $entity) {
            $endpoints = array();
            foreach ($entity->getEndpoints() as $endpoint) {
                $endpoints[] = $endpoint->endpointId;
            }

            $events = array();
            foreach ($entity->getEvents() as $event) {
                $events[] = $event->eventType;
            }

            $farms = array();
            foreach ($entity->getFarms() as $farm) {
                if ($farm->farmId) {
                    $farms[] =$farm->farmId;
                }
            }

            $list[] = array(
                'webhookId' => $entity->webhookId,
                'name'      => $entity->name,
                'postData'  => $entity->postData,
                'skipPrivateGv' => $entity->skipPrivateGv,
                'endpoints' => $endpoints,
                'events'    => $events,
                'farms'     => $farms
            );
        }

        return $list;
    }

}
