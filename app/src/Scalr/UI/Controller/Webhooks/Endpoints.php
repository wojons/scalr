<?php
use Scalr\Acl\Acl;
use Scalr\Model\Entity\WebhookEndpoint;
use Scalr\Model\Entity\WebhookConfigEndpoint;

class Scalr_UI_Controller_Webhooks_Endpoints extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'endpointId';

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/webhooks/endpoints/view.js',
            array(),
            array('ui/webhooks/dataconfig.js'),
            array(),
            array('webhooks.endpoints', 'webhooks.configs')
        );
    }

    public function xRemoveAction()
    {
        $endpoint = WebhookEndpoint::findPk($this->request->getParam('endpointId'));
        if ($endpoint->envId != $this->getEnvironmentId() || $endpoint->accountId != $this->getEnvironment()->clientId) {
            throw new Scalr_Exception_Core('Insufficient permissions to remove endpoint');
        }

        if (count(WebhookConfigEndpoint::findByEndpointId($endpoint->endpointId)) > 0) {
            throw new Scalr_Exception_Core('Endpoint is used by webhooks and can\'t be removed');
        }
        //todo: check is endpoint in use and forbid deleting
        $endpoint->delete();
        $this->response->success("Endpoint successfully removed");
    }

    public function xSaveAction($url, $endpointId = null)
    {
        if (!$endpointId) {
            $endpoint = new WebhookEndpoint();
            $endpoint->level = WebhookEndpoint::LEVEL_ENVIRONMENT;
            $endpoint->accountId = $this->getEnvironment()->clientId;
            $endpoint->envId = $this->getEnvironmentId();
            $endpoint->securityKey = Scalr::GenerateRandomKey(64);
        } else {
            $endpoint = WebhookEndpoint::findPk($endpointId);
            if ($endpoint->envId != $this->getEnvironmentId() || $endpoint->accountId != $this->getEnvironment()->clientId) {
                throw new Scalr_Exception_Core('Insufficient permissions to edit endpoint');
            }
        }

        foreach (WebhookEndpoint::findByUrl($url) as $ep) {
            if ($ep->endpointId != $endpoint->endpointId && $ep->envId == $this->getEnvironmentId()) {
                $this->request->addValidationErrors('url', 'Endpoint url must be unique within environment');
                break;
            }
        }

        if ($this->request->isValid()) {
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
                    'securityKey'     => $endpoint->securityKey
                )
            ));
        } else {
            $this->response->data($this->request->getValidationErrors());
            $this->response->failure();
        }
    }

    public function xGroupActionHandlerAction()
    {
        $this->request->defineParams(array(
            'endpointIds' => array('type' => 'json'), 'action'
        ));

        $processed = array();
        $errors = array();

        $endpoints = WebhookEndpoint::find(array(
            array('accountId' => $this->getEnvironment()->clientId),
            array('envId'     => $this->getEnvironmentId()),
            array(
                'endpointId'  => array('$in' => $this->getParam('endpointIds'))
            )
        ));
        foreach($endpoints as $endpoint) {
            //todo: check is endpoint in use and forbid deleting
            if (count(WebhookConfigEndpoint::findByEndpointId($endpoint->endpointId)) == 0) {
                $processed[] = $endpoint->endpointId;
                $endpoint->delete();
            } else {
                $errors[] = 'Endpoint is used by webhooks and can\'t be removed';
            }
        }

        $num = count($this->getParam('endpointIds'));
        if (count($processed) == $num) {
            $this->response->success('Endpoints successfully processed');
        } else {
            array_walk($errors, function(&$item) { $item = '- ' . $item; });
            $this->response->warning(sprintf("Successfully processed only %d from %d endpoints. \nFollowing errors occurred:\n%s", count($processed), $num, join($errors, '')));
        }

        $this->response->data(array('processed' => $processed));
    }

    public function xValidateAction()
    {
        $endpoint = WebhookEndpoint::findPk($this->request->getParam('endpointId'));
        if ($endpoint->envId != $this->getEnvironmentId() || $endpoint->accountId != $this->getEnvironment()->clientId) {
            throw new Scalr_Exception_Core('Insufficient permissions to edit endpoint');
        }

        if ($endpoint->url != $this->request->getParam('url')) {
            $endpoint->url = $this->request->getParam('url');
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


}
