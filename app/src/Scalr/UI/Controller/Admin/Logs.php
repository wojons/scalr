<?php

class Scalr_UI_Controller_Admin_Logs extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return $this->user->isScalrAdmin();
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $farms = array();
        foreach ($this->db->GetAll("SELECT id, name FROM farms") as $key => $value) {
            $farms[$value['id']] = $value['name'];
        }
        $farms[0] = 'All farms';
        $this->response->page('ui/admin/logs/view.js', array(
            'farms' => $farms
        ));
    }

    public function xListLogsAction()
    {
        $this->request->defineParams(array(
            'farmId' => array('type' => 'int'),
            'severity' => array('type' => 'array'),
            'sort' => array('type' => 'json')
        ));
        $sql = "SELECT transactionid, dtadded, message FROM syslog WHERE :FILTER:";
        $args = array();

        if ($this->getParam('farmId')) {
            $sql .= ' AND farmId = ?';
            $args[] = $this->getParam('farmId');
        }

        $severity = array();
        foreach ($this->getParam('severity') as $key => $value) {
            if ($value == '1' && in_array($key, array('INFO', 'WARN', 'ERROR', 'FATAL')))
                $severity[] = $this->db->qstr($key);
        }

        if (count($severity))
            $sql .= ' AND severity IN(' . join($severity, ',') . ')';

        if ($this->getParam('toDate')) {
            $sql .= ' AND TO_DAYS(dtadded) = TO_DAYS(?)';
            $args[] = $this->getParam('toDate');
        }

        $sql .= ' GROUP BY transactionid';

        $response = $this->buildResponseFromSql2($sql, array('dtadded'), array('message'), $args);
        foreach ($response['data'] as &$row) {
            $meta = $this->db->GetRow('SELECT * FROM syslog_metadata WHERE transactionid = ? LIMIT 1', array($row['transactionid']));
            $row['warn'] = $meta['warnings'] ? $meta['warnings'] : 0;
            $row['err'] = $meta['errors'] ? $meta['errors'] : 0;
        }

        $this->response->data($response);
    }

    public function detailsAction()
    {
        $this->response->page('ui/admin/logs/details.js');
    }

    public function xListDetailsAction()
    {
        $this->request->defineParams(array(
            'severity' => array('type' => 'array'),
            'sort' => array('type' => 'json')
        ));

        $sql = "SELECT id, dtadded, message, severity, transactionid, caller FROM syslog WHERE transactionid = ? AND :FILTER:";

        $severity = array();
        foreach ($this->getParam('severity') as $key => $value) {
            if ($value == '1' && in_array($key, array('INFO', 'WARN', 'ERROR', 'FATAL')))
                $severity[] = $this->db->qstr($key);
        }

        if (count($severity))
            $sql .= " AND severity IN (" . join($severity, ',') . ")";

        $response = $this->buildResponseFromSql2($sql, array('dtadded', 'severity', 'id'), array('caller', 'message'), array($this->getParam('trnId')));
        $this->response->data($response);
    }
}