<?php

use Scalr\Acl\Acl;

class Scalr_UI_Controller_Dashboard_Widget_Lasterrors extends Scalr_UI_Controller_Dashboard_Widget
{
    public function getDefinition()
    {
        return [
            'type' => 'local'
        ];
    }

    public function getContent($params = [])
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_SYSTEM_LOGS);

        $params['errorCount'] = isset($params['errorCount']) ? intval($params['errorCount']) : 0;
        if ($params['errorCount'] < 5 || $params['errorCount'] > 100) {
            $params['errorCount'] = 10;
        }

        $sql = "SELECT l.time, l.message, l.serverid as server_id, l.cnt
            FROM logentries l
            INNER JOIN farms f ON f.id = l.farmid
            WHERE l.severity = 4
            AND f.env_id = ? AND " . $this->request->getFarmSqlQuery();
        $args = [$this->getEnvironmentId()];

        $sql .= " ORDER BY time DESC LIMIT 0, ?";
        $args[] = $params['errorCount'];

        $r = $this->db->Execute($sql, $args);

        $retval = [];
        while ($value = $r->FetchRow()) {
            $value['message'] = htmlspecialchars($value['message']);
            $value['time'] = date('H:i:s, M d', $value["time"]);
            $retval[] = $value;
        }
        return $retval;
    }
}
