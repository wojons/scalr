<?php
use Scalr\Acl\Acl;
use Scalr\Model\Entity\WebhookHistory;
use Scalr\Model\Entity\WebhookConfig;

class Scalr_UI_Controller_Webhooks_History extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return ($this->request->getScope() == WebhookConfig::SCOPE_ENVIRONMENT && $this->request->isAllowed(Acl::RESOURCE_WEBHOOKS_ENVIRONMENT) && !$this->user->isScalrAdmin() ||
               $this->request->getScope() == WebhookConfig::SCOPE_ACCOUNT && $this->request->isAllowed(Acl::RESOURCE_WEBHOOKS_ACCOUNT) && !$this->user->isScalrAdmin() ||
               $this->request->getScope() == WebhookConfig::SCOPE_SCALR && $this->user->isScalrAdmin());
    }

    private function canViewPayload($webhook)
    {
        return $webhook->getScope() == WebhookConfig::SCOPE_SCALR ||
               $webhook->getScope() == WebhookConfig::SCOPE_ACCOUNT && $webhook->accountId == $this->user->getAccountId() ||
               $webhook->getScope() == WebhookConfig::SCOPE_ENVIRONMENT && $webhook->envId == $this->getEnvironmentId() && $webhook->accountId == $this->user->getAccountId();
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/webhooks/history/view.js', [], array('ui/webhooks/webhooks.js'));
    }

    /**
     * @param string $eventId optional
     * @throws Exception
     */
    public function xGetListAction($eventId = null)
    {
        $hist = new WebhookHistory();

        $sql = "SELECT " . $hist->fields('h') . ", w.name AS webhookName, e.url
                FROM " . $hist->table() . " h
                INNER JOIN webhook_endpoints e ON h.endpoint_id = e.endpoint_id
                INNER JOIN webhook_configs w ON h.webhook_id = w.webhook_id
                WHERE :FILTER:
        ";

        if ($eventId) {
            $sql .= ' AND h.event_id = ?';
            $args[] = $eventId;
        }

        if ($this->request->getScope() == WebhookConfig::SCOPE_SCALR) {
            $sql .= ' AND w.account_id IS NULL';
            $sql .= ' AND w.env_id IS NULL';
        } elseif ($this->request->getScope() == WebhookConfig::SCOPE_ACCOUNT) {
            $sql .= ' AND (w.account_id = ? OR w.account_id IS NULL)';
            $args[] = $this->user->getAccountId();
            $sql .= ' AND w.env_id IS NULL';
        } elseif ($this->request->getScope() == WebhookConfig::SCOPE_ENVIRONMENT) {
            $sql .= ' AND (w.account_id = ? OR w.account_id IS NULL)';
            $args[] = $this->user->getAccountId();
            $sql .= ' AND (w.env_id = ? OR w.env_id IS NULL)';
            $args[] = $this->getEnvironmentId();
        }

        $response = $this->buildResponseFromSql2($sql, ['created'], ['e.url', 'h.event_type', 'h.payload'], $args);

        foreach ($response['data'] as $index => $row) {
            $hist = new WebhookHistory();
            $hist->load($row);

            $item = [];
            foreach (get_object_vars($hist) as $k => $v) {
                $item[$k] = $v;
            }
            $item['url'] = $row['url'];
            $item['webhookName'] = $row['webhookName'];
            $item['created'] = Scalr_Util_DateTime::convertTz($hist->created);
            unset($item['payload']);
            unset($hist);
            $response['data'][$index] = $item;
        }

        $this->response->data($response);
    }

    /**
     * @param string $historyId
     * @throws Exception
     */
    public function xGetInfoAction($historyId)
    {
        $history = WebhookHistory::findPk($historyId);
        $webhook = WebhookConfig::findPk($history->webhookId);
        if (!$this->canViewPayload($webhook)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }
        $this->response->data(array(
            'info' => array(
                'historyId' => $history->historyId,
                'payload' => $history->payload
            )
        ));
    }

}
