<?php
use Scalr\Acl\Acl;
use Scalr\Model\Entity\WebhookHistory;
use Scalr\Model\Entity\WebhookConfig;

class Scalr_UI_Controller_Webhooks_History extends Scalr_UI_Controller
{
    private static $levelMap = [
        'environment' => WebhookConfig::LEVEL_ENVIRONMENT,
        'account'     => WebhookConfig::LEVEL_ACCOUNT,
        'scalr'       => WebhookConfig::LEVEL_SCALR
    ];

    var $level = null;

    public function hasAccess()
    {
        return ($this->level == WebhookConfig::LEVEL_ENVIRONMENT && $this->request->isAllowed(Acl::RESOURCE_ENVADMINISTRATION_WEBHOOKS) && !$this->user->isScalrAdmin() ||
               $this->level == WebhookConfig::LEVEL_ACCOUNT && $this->request->isAllowed(Acl::RESOURCE_ADMINISTRATION_WEBHOOKS) && !$this->user->isScalrAdmin() ||
               $this->level == WebhookConfig::LEVEL_SCALR && $this->user->isScalrAdmin());
    }

    private function canViewPayload($webhook)
    {
        return $webhook->level == WebhookConfig::LEVEL_SCALR && empty($webhook->accountId) && empty($webhook->envId) ||
                $webhook->level == WebhookConfig::LEVEL_ACCOUNT && $webhook->accountId == $this->user->getAccountId() && empty($webhook->envId) ||
                $webhook->level == WebhookConfig::LEVEL_ENVIRONMENT && $webhook->envId == $this->getEnvironmentId() && $webhook->accountId == $this->user->getAccountId();
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
        } elseif (in_array($level, [WebhookConfig::LEVEL_ENVIRONMENT, WebhookConfig::LEVEL_ACCOUNT, WebhookConfig::LEVEL_SCALR])) {
            $this->level = (int)$level;
        } else {
            throw new Scalr_Exception_Core('Invalid webhook scope');
        }
    }
    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/webhooks/history/view.js', [
            'level' => $this->level,
            'levelMap' => array_flip(self::$levelMap)
        ],
        array('ui/webhooks/webhooks.js'));
    }

    /**
     * @param string $eventId optional
     * @throws Exception
     */
    public function xGetListAction($eventId = null)
    {
        $this->request->defineParams(array(
            'query',
            'sort' => array('type' => 'json')
        ));

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

        if ($this->level == WebhookConfig::LEVEL_SCALR) {
            $sql .= ' AND w.account_id IS NULL';
            $sql .= ' AND w.env_id IS NULL';
        } elseif ($this->level == WebhookConfig::LEVEL_ACCOUNT) {
            $sql .= ' AND (w.account_id = ? OR w.account_id IS NULL)';
            $args[] = $this->user->getAccountId();
            $sql .= ' AND w.env_id IS NULL';
        } elseif ($this->level == WebhookConfig::LEVEL_ENVIRONMENT) {
            $sql .= ' AND (w.account_id = ? OR w.account_id IS NULL)';
            $args[] = $this->user->getAccountId();
            $sql .= ' AND (w.env_id = ? OR w.env_id IS NULL)';
            $args[] = $this->getEnvironmentId();
        }

        $response = $this->buildResponseFromSql2($sql, array('created'), array('e.url', 'h.event_type'), $args);

        foreach ($response['data'] as $index => $row) {
            $hist = new WebhookHistory();
            $hist->load($row);

            $item = array();
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
