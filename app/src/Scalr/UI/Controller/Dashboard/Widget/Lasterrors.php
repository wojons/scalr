<?php

use Scalr\Acl\Acl;

class Scalr_UI_Controller_Dashboard_Widget_Lasterrors extends Scalr_UI_Controller_Dashboard_Widget
{
    public function getDefinition()
    {
        return array(
            'type' => 'local'
        );
    }

    public function getContent($params = array())
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_SYSTEM_LOGS);

        if (!$params['errorCount'])
            $params['errorCount'] = 10;

        $sql = 'SELECT l.time, l.message, l.serverid as server_id
            FROM logentries l
            INNER JOIN farms f ON f.id = l.farmid
            WHERE l.severity = 4
            AND f.env_id = ?';
        $params = array($this->getEnvironmentId());

        $allFarms = $this->request->isAllowed(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_NOT_OWNED_FARMS);
        if (! $allFarms) {
            $sql .= ' AND f.created_by_id = ?';
            $params[] = $this->user->getId();
        }

        $sql .= 'GROUP BY message, source ORDER BY time DESC LIMIT 0, ?';
        $params[] = intval($params['errorCount']);

        $r = $this->db->Execute($sql, $params);

        $retval = array();
        while ($value = $r->FetchRow()) {
            $value['message'] = htmlspecialchars($value['message']);
            $value['time'] = date('H:i:s, M d',$value["time"]);
            $retval[] = $value;
        }
        return $retval;
    }
}
