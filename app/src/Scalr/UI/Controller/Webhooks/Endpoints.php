<?php
use Scalr\Acl\Acl;
use Scalr\Model\Entity\WebhookEndpoint;
use Scalr\Model\Entity\WebhookConfig;
use Scalr\Model\Entity\WebhookConfigEndpoint;
use Scalr\UI\Request\JsonData;
use Scalr\UI\Request\Validator;
use Scalr\DataType\ScopeInterface;

class Scalr_UI_Controller_Webhooks_Endpoints extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'endpointId';

    public function hasAccess()
    {
        return ($this->request->getScope() == WebhookEndpoint::SCOPE_ENVIRONMENT && $this->request->isAllowed(Acl::RESOURCE_WEBHOOKS_ENVIRONMENT) && !$this->user->isScalrAdmin() ||
               $this->request->getScope() == WebhookEndpoint::SCOPE_ACCOUNT && $this->request->isAllowed(Acl::RESOURCE_WEBHOOKS_ACCOUNT) && !$this->user->isScalrAdmin() ||
               $this->request->getScope() == WebhookEndpoint::SCOPE_SCALR && $this->user->isScalrAdmin());
    }

    /**
     * @param WebhookEndpoint $endpoint
     * @return bool
     * @throws Exception
     */
    private function canManageEndpoint($endpoint)
    {
        return $this->request->getScope() == $endpoint->getScope() &&
               ($endpoint->getScope() == WebhookEndpoint::SCOPE_SCALR ||
                $endpoint->getScope() == WebhookEndpoint::SCOPE_ACCOUNT && $endpoint->accountId == $this->user->getAccountId() && $this->request->isAllowed(Acl::RESOURCE_WEBHOOKS_ACCOUNT, Acl::PERM_WEBHOOKS_ACCOUNT_MANAGE) ||
                $endpoint->getScope() == WebhookEndpoint::SCOPE_ENVIRONMENT && $endpoint->envId == $this->getEnvironmentId() && $endpoint->accountId == $this->user->getAccountId() && $this->request->isAllowed(Acl::RESOURCE_WEBHOOKS_ENVIRONMENT, Acl::PERM_WEBHOOKS_ENVIRONMENT_MANAGE));
    }


    public function defaultAction()
    {
        $this->response->page('ui/webhooks/endpoints/view.js',
            array(
                'endpoints' => $this->getList(),
                'scope' => $this->request->getScope()
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

        if (!$this->canManageEndpoint($endpoint)) {
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
            $endpoint->setScope($this->request->getScope(), $this->user->getAccountId(), $this->getEnvironmentId(true));
            $endpoint->securityKey = Scalr::GenerateRandomKey(64);
        } else {
            $endpoint = WebhookEndpoint::findPk($endpointId);

            if (!$this->canManageEndpoint($endpoint)) {
                throw new Scalr_Exception_Core('Insufficient permissions to edit endpoint at this scope');
            }
        }

        $validator = new Validator();
        $validator->validate($url, 'url', Validator::URL);

        //check url unique within current level
        $criteria = [];
        $criteria[] = ['url' => $url];
        if ($endpoint->endpointId) {
            $criteria[] = ['endpointId' => ['$ne' => $endpoint->endpointId]];
        }
        switch ($this->request->getScope()) {
            case WebhookEndpoint::SCOPE_ENVIRONMENT:
                $criteria[] = ['level' => WebhookEndpoint::LEVEL_ENVIRONMENT];
                $criteria[] = ['envId' => $endpoint->envId];
                $criteria[] = ['accountId' => $endpoint->accountId];
                break;
            case WebhookEndpoint::SCOPE_ACCOUNT:
                $criteria[] = ['level' => WebhookEndpoint::LEVEL_ACCOUNT];
                $criteria[] = ['envId' => null];
                $criteria[] = ['accountId' => $endpoint->accountId];
                break;
            case WebhookEndpoint::SCOPE_SCALR:
                $criteria[] = ['level' => WebhookEndpoint::LEVEL_SCALR];
                $criteria[] = ['envId' => null];
                $criteria[] = ['accountId' => null];
                break;
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

        $this->response->success('Endpoint successfully saved');

        $this->response->data(array(
            'endpoint' => array(
                'endpointId'      => $endpoint->endpointId,
                'url'             => $endpoint->url,
                'isValid'         => $endpoint->isValid,
                'validationToken' => $endpoint->validationToken,
                'securityKey'     => $endpoint->securityKey,
                'scope'           => $endpoint->getScope()
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
            $endpoints = WebhookEndpoint::find([['endpointId'  => ['$in' => $endpointIds]]]);
            foreach ($endpoints as $endpoint) {
                if (!$this->canManageEndpoint($endpoint)) {
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
            $this->response->success('Selected endpoint(s) successfully removed');
        } else {
            array_walk($errors, function(&$item) { $item = '- ' . $item; });
            $this->response->warning(sprintf("Successfully removed only %d from %d endpoints. \nFollowing errors occurred:\n%s", count($processed), $num, join($errors, '')));
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

        if (!$this->canManageEndpoint($endpoint)) {
            throw new Scalr_Exception_Core('Insufficient permissions to edit endpoint in this scope');
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

    /**
     * @param   string      $scope
     */
    public function xListAction($scope = '')
    {
        $this->response->data(array('endpoints' => $this->getList($scope)));
    }

    /**
     * @param   string      $scope
     * @return  array
     * @throws Scalr_Exception_Core
     */
    private function getList($scope = '')
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

        $scopeLinking = [
            ScopeInterface::SCOPE_SCALR => WebhookEndpoint::LEVEL_SCALR,
            ScopeInterface::SCOPE_ACCOUNT => WebhookEndpoint::LEVEL_ACCOUNT,
            ScopeInterface::SCOPE_ENVIRONMENT => WebhookEndpoint::LEVEL_ENVIRONMENT
        ];

        if ($scope && array_key_exists($scope, $scopeLinking)) {
            $criteria[] = ['level' => $scopeLinking[$scope]];
        }

        foreach (WebhookEndpoint::find($criteria) as $entity) {
            $webhooks = array();
            foreach (WebhookConfigEndpoint::findByEndpointId($entity->endpointId) as $WebhookConfigEndpoint) {
                $webhookConfigEntity = WebhookConfig::findPk($WebhookConfigEndpoint->webhookId);
                $webhooks[] = [
                    'webhookId' => $webhookConfigEntity->webhookId,
                    'name'      => $webhookConfigEntity->name,
                    'scope'     => $webhookConfigEntity->getScope(),
                    'envId'     => $webhookConfigEntity->envId
                ];
            }

            $endpoint = array(
                'endpointId'      => $entity->endpointId,
                'url'             => $entity->url,
                'scope'           => $entity->getScope()
            );
            if ($this->request->getScope() == $entity->getScope()) {
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
