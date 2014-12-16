<?php
use Scalr\Acl\Acl;
use Scalr\Model\Entity\WebhookEndpoint;
use Scalr\Model\Entity\WebhookConfig;
use Scalr\Model\Entity\WebhookConfigEndpoint;
use Scalr\UI\Request\JsonData;
use Scalr\UI\Request\Validator;

class Scalr_UI_Controller_Webhooks_Endpoints extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'endpointId';
    
    private static $levelMap = [
        'environment' => WebhookEndpoint::LEVEL_ENVIRONMENT,
        'account'     => WebhookEndpoint::LEVEL_ACCOUNT,
        'scalr'       => WebhookEndpoint::LEVEL_SCALR
    ];

    var $level = null;
    
    public function hasAccess()
    {
        return ($this->level == WebhookEndpoint::LEVEL_ENVIRONMENT && $this->request->isAllowed(Acl::RESOURCE_ENVADMINISTRATION_WEBHOOKS) && !$this->user->isScalrAdmin() ||
               $this->level == WebhookEndpoint::LEVEL_ACCOUNT && $this->request->isAllowed(Acl::RESOURCE_ADMINISTRATION_WEBHOOKS) && !$this->user->isScalrAdmin() ||
               $this->level == WebhookEndpoint::LEVEL_SCALR && $this->user->isScalrAdmin());
    }
    
    public function init()
    {
        if ($this->user->isScalrAdmin()) {
            $level = 'scalr';
        } else {
            $level = $this->getParam('level') ? $this->getParam('level') : 'environment';
        }
        if (isset(self::$levelMap[$level])) {
            $this->level = self::$levelMap[$level];
        } elseif (in_array($level, [WebhookEndpoint::LEVEL_ENVIRONMENT, WebhookEndpoint::LEVEL_ACCOUNT, WebhookEndpoint::LEVEL_SCALR])) {
            $this->level = (int)$level;
        } else {
            throw new Scalr_Exception_Core('Invalid webhook scope');
        }
    }
    
    /**
     * @param WebhookEndpoint $endpoint
     * @throws Exception
     */
    private function canEditEndpoint($endpoint)
    {
        return $this->level == $endpoint->level &&
               ($endpoint->level == WebhookEndpoint::LEVEL_ENVIRONMENT && $endpoint->envId == $this->getEnvironmentId() && $endpoint->accountId == $this->user->getAccountId() ||
               $endpoint->level == WebhookEndpoint::LEVEL_ACCOUNT && $endpoint->accountId == $this->user->getAccountId() && empty($endpoint->envId) ||
               $endpoint->level == WebhookEndpoint::LEVEL_SCALR && empty($endpoint->accountId) && empty($endpoint->envId));
    }


    public function defaultAction()
    {
        $this->response->page('ui/webhooks/endpoints/view.js',
            array(
                'endpoints' => $this->getList(),
                'level' => $this->level,
                'levelMap' => array_flip(self::$levelMap)
            ),
            array('ui/webhooks/webhooks.js')
        );
    }

    /**
     * @param string $endpointId
     * @throws Exception
     */
    public function xRemoveAction($endpointId)
    {
        $endpoint = WebhookEndpoint::findPk($endpointId);
        if (!$this->canEditEndpoint($endpoint)) {
            throw new Scalr_Exception_Core('Insufficient permissions to remove endpoint');
        }

        if (count(WebhookConfigEndpoint::findByEndpointId($endpoint->endpointId)) > 0) {
            throw new Scalr_Exception_Core('Endpoint is used by webhooks and can\'t be removed');
        }
        $endpoint->delete();
        $this->response->success("Endpoint successfully removed");
    }

    /**
     * @param string $url
     * @param string $endpointId
     * @throws Exception
     */
    public function xSaveAction($url, $endpointId = null)
    {
        if (!$endpointId) {
            $endpoint = new WebhookEndpoint();
            $endpoint->level = $this->level;
            if ($this->level == WebhookEndpoint::LEVEL_ENVIRONMENT) {
                $endpoint->accountId = $this->user->getAccountId();
                $endpoint->envId = $this->getEnvironmentId();
            } elseif ($this->level == WebhookEndpoint::LEVEL_ACCOUNT) {
                $endpoint->accountId = $this->user->getAccountId();
            }
            $endpoint->securityKey = Scalr::GenerateRandomKey(64);
        } else {
            $endpoint = WebhookEndpoint::findPk($endpointId);
            if (!$this->canEditEndpoint($endpoint)) {
                throw new Scalr_Exception_Core('Insufficient permissions to edit endpoint at this scope');
            }
        }
        
        $validator = new Validator();
        $validator->validate($url, 'url', Validator::NOEMPTY);

        //check url unique within current level
        $criteria = [];
        $criteria[] = ['url' => $url];
        $criteria[] = ['level' => $this->level];
        if ($endpoint->endpointId) {
            $criteria[] = ['endpointId' => ['$ne' => $endpoint->endpointId]];
        }
        if ($this->level == WebhookEndpoint::LEVEL_ENVIRONMENT) {
            $criteria[] = ['envId' => $endpoint->envId];
            $criteria[] = ['accountId' => $endpoint->accountId];
        } elseif ($this->level == WebhookEndpoint::LEVEL_ACCOUNT) {
            $criteria[] = ['envId' => null];
            $criteria[] = ['accountId' => $endpoint->accountId];
        } elseif ($this->level == WebhookEndpoint::LEVEL_SCALR) {
            $criteria[] = ['envId' => null];
            $criteria[] = ['accountId' => null];
        }

        if (WebhookEndpoint::findOne($criteria)) {
            $validator->addError('url', 'Endpoint url must be unique within current scope');
        }

        if (!$validator->isValid($this->response))
            return;


        if ($endpoint->url != $url) {
            $endpoint->isValid = false;
            $endpoint->url = $url;
        }
        ////temporarily disable url validation per Igor`s request(see also webhooks/endpoints/view.js)
        $endpoint->isValid = true;

        $endpoint->save();

        $this->response->data(array(
            'endpoint' => array(
                'endpointId'      => $endpoint->endpointId,
                'url'             => $endpoint->url,
                'isValid'         => $endpoint->isValid,
                'validationToken' => $endpoint->validationToken,
                'securityKey'     => $endpoint->securityKey,
                'level'           => $endpoint->level
            )
        ));
    }

    /**
     * @param JsonData $endpointIds
     * @throws Exception
     */
    public function xGroupActionHandlerAction(JsonData $endpointIds)
    {
        $processed = array();
        $errors = array();
        if (!empty($endpointIds)) {
            $endpoints = WebhookEndpoint::find(array(
                array(
                    'endpointId'  => array('$in' => $endpointIds)
                )
            ));
            foreach($endpoints as $endpoint) {
                if (!$this->canEditEndpoint($endpoint)) {
                    $errors[] = 'Insufficient permissions to remove endpoint';
                } elseif (count(WebhookConfigEndpoint::findByEndpointId($endpoint->endpointId)) == 0) {
                    $processed[] = $endpoint->endpointId;
                    $endpoint->delete();
                } else {
                    $errors[] = 'Endpoint is used by webhooks and can\'t be removed';
                }
            }
        }
        
        $num = count($endpointIds);
        if (count($processed) == $num) {
            $this->response->success('Endpoints successfully processed');
        } else {
            array_walk($errors, function(&$item) { $item = '- ' . $item; });
            $this->response->warning(sprintf("Successfully processed only %d from %d endpoints. \nFollowing errors occurred:\n%s", count($processed), $num, join($errors, '')));
        }

        $this->response->data(array('processed' => $processed));
    }

    /**
     * @param string $endpointId
     * @param string $url
     * @throws Exception
     */
    public function xValidateAction($endpointId, $url)
    {
        $endpoint = WebhookEndpoint::findPk($endpointId);
        if (!$this->canEditEndpoint($endpoint)) {
            throw new Scalr_Exception_Core('Insufficient permissions to edit endpoint at this level');
        }

        if ($endpoint->url != $url) {
            $endpoint->url = $url;
            $endpoint->save();
        }

        if ($endpoint->validateUrl()) {
            $this->response->success(sprintf('Endpoint %s successfully validated', $endpoint->url));
        } else {
            $this->response->failure(sprintf('Unable to validate endpoint %s. Token was not returned.', $endpoint->url));
        }

        $this->response->data(array(
            'endpoint' => array(
                'endpointId'      => $endpoint->endpointId,
                'url'             => $endpoint->url,
                'isValid'         => $endpoint->isValid,
                'validationToken' => $endpoint->validationToken,
                'securityKey'     => $endpoint->securityKey
            )
        ));
    }

    public function xListAction()
    {
        $this->response->data(array('endpoints' => $this->getList()));
    }

    private function getList()
    {
        $endpoints = array();

        $criteria = [];
        
        if ($this->level == WebhookEndpoint::LEVEL_ENVIRONMENT) {
            $criteria[] = ['$or' => [
                ['$and' => [['accountId' => $this->user->getAccountId()], ['envId' => $this->getEnvironmentId()], ['level' => WebhookEndpoint::LEVEL_ENVIRONMENT]]],
                ['$and' => [['accountId' => $this->user->getAccountId()], ['envId' => null], ['level' => WebhookEndpoint::LEVEL_ACCOUNT]]],
                ['$and' => [['accountId' => null], ['envId' => null], ['level' => WebhookEndpoint::LEVEL_SCALR]]]
            ]];
        } elseif ($this->level == WebhookEndpoint::LEVEL_ACCOUNT) {
            $criteria[] = ['$or' => [
                ['$and' => [['accountId' => $this->user->getAccountId()], ['envId' => null], ['level' => WebhookEndpoint::LEVEL_ACCOUNT]]],
                ['$and' => [['accountId' => null], ['envId' => null], ['level' => WebhookEndpoint::LEVEL_SCALR]]]
            ]];
        } elseif ($this->level == WebhookEndpoint::LEVEL_SCALR) {
            $criteria[] = ['level' => WebhookEndpoint::LEVEL_SCALR];
            $criteria[] = ['envId' => null];
            $criteria[] = ['accountId' => null];
        }
        
        foreach (WebhookEndpoint::find($criteria) as $entity) {
            $webhooks = array();
            foreach (WebhookConfigEndpoint::findByEndpointId($entity->endpointId) as $WebhookConfigEndpoint) {
                $webhooks[$WebhookConfigEndpoint->webhookId] = WebhookConfig::findPk($WebhookConfigEndpoint->webhookId)->name;
            }

            $endpoint = array(
                'endpointId'      => $entity->endpointId,
                'url'             => $entity->url,
                'level'           => $entity->level
            );
            if ($this->level == $entity->level) {
                $endpoint['isValid'] = $entity->isValid;
                $endpoint['validationToken'] = $entity->validationToken;
                $endpoint['securityKey'] = $entity->securityKey;
                $endpoint['webhooks'] = $webhooks;
            }
            $endpoints[] = $endpoint;
        }

        return $endpoints;

    }
}
