<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20131212000101 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = 'e0d6f210-b031-4ef2-8cc5-fbadbb1ef6c0';

    protected $depends = array();

    protected $description = 'SCALRCORE-820 Fix';

    protected $ignoreChanges = true;

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
        return $this->hasTable('syslog_metadata');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->db->Execute("
            CREATE TABLE `syslog_metadata` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `transactionid` varchar(50) DEFAULT NULL,
              `errors` int(5) DEFAULT NULL,
              `warnings` int(5) DEFAULT NULL,
              `message` varchar(255) DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `transid` (`transactionid`)
            ) ENGINE=MyISAM
        ");
    }
}