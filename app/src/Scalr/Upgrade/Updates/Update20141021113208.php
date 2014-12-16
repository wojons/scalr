<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20141021113208 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '8e2eac24-544b-486c-83a5-7efdad30e392';

    protected $depends = [];

    protected $description = "Init os_type to server properties";

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    protected function isApplied1($stage)
    {
        return false;
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->db->Execute("
            INSERT IGNORE `server_properties` (`server_id`, `name`, `value`)
            SELECT s.server_id, 'os_type' `name`, s.os_type `value`
            FROM servers s
            WHERE s.os_type IS NOT NULL
        ");
    }
}