<?php
use Scalr\Acl\Acl;
use Scalr\Model\Entity\WebhookHistory;

class Scalr_UI_Controller_Webhooks_History extends Scalr_UI_Controller
{
    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/webhooks/history/view.js');
    }

    public function xGetListAction()
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
                WHERE e.env_id = ?
                AND :FILTER:
        ";

        $args = array($this->getEnvironmentId());

        if ($this->getParam('eventId')) {
            $sql .= ' AND h.event_id = ?';
            $args[] = $this->getParam('eventId');
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

            unset($hist);
            $response['data'][$index] = $item;
        }

        $this->response->data($response);
    }

    public function xGetInfoAction()
    {
        $history = WebhookHistory::findPk($this->getParam('historyId'));

        $this->response->data(array(
            'info' => array(
                'historyId' => $history->historyId,
                'payload' => $history->payload
            )
        ));
    }

}
