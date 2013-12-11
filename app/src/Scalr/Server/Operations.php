<?php
namespace Scalr\Server;

class Operations
{
    const MYSQL_GROW_VOLUME = 'mysql.grow-volume';

    private $dbServer;

    public function __construct(\DBServer $dbServer) {
        $this->dbServer = $dbServer;
        $this->db = \Scalr::getDb();
    }

    public function add($operationId, $type) {
        $this->db->Execute("INSERT INTO server_operations SET
            `id` = ?,
            `server_id` = ?,
            `timestamp` = ?,
            `status`	= ?,
            `name` = ?,
            `phases` = ?
        ", array(
            $operationId,
            $this->dbServer->serverId,
            time(),
            'in-progress',
            $type,
            ''
        ));
    }
}
