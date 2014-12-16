<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140904074648 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '761821fa-c48c-43a1-bfaa-0fbf7fe30213';

    protected $depends = [];

    protected $description = "Webhooks structure changes";

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 4;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('webhook_configs') && $this->hasTableColumn('webhook_configs', 'timeout');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('webhook_configs');
    }

    protected function run1($stage)
    {
        $this->console->out("Adding webhook_configs timeout");
        $this->db->Execute("ALTER TABLE `webhook_configs` ADD `timeout` int(2) NOT NULL DEFAULT '3'");
    }

    protected function isApplied2($stage)
    {
        return $this->hasTable('webhook_configs') && $this->hasTableColumn('webhook_configs', 'attempts');
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('webhook_configs');
    }

    protected function run2($stage)
    {
        $this->console->out("Adding webhook_configs attempts");
        $this->db->Execute("ALTER TABLE `webhook_configs` ADD `attempts` int(2) NOT NULL DEFAULT '3'");
    }

    protected function isApplied3($stage)
    {
        return $this->hasTable('webhook_history') && $this->hasTableColumn('webhook_history', 'handle_attempts');
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTable('webhook_history');
    }

    protected function run3($stage)
    {
        $this->console->out("Adding webhook_history handle_attempts");
        $this->db->Execute("ALTER TABLE `webhook_history` ADD `handle_attempts` int(2) DEFAULT '0'");
    }

    protected function isApplied4($stage)
    {
        return $this->hasTable('webhook_history') && $this->hasTableColumn('webhook_history', 'dtlasthandleattempt');
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTable('webhook_history');
    }

    protected function run4($stage)
    {
        $this->console->out("Adding webhook_history dtlasthandleattempt");
        $this->db->Execute("ALTER TABLE `webhook_history` ADD `dtlasthandleattempt` datetime DEFAULT NULL");
    }


}