<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150706111254 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '3a46f141-c5e2-4983-b1c8-82edfcf18fbd';

    protected $depends = [];

    protected $description = "Add aws_billing_records table";

    protected $ignoreChanges = true;

    protected $dbservice = 'cadb';

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::isRefused()
     */
    public function isRefused()
    {
        return !$this->container->analytics->enabled ? "Cost analytics is turned off" : false;
    }

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
        return $this->hasTable('aws_billing_records');
    }

    protected function run1($stage)
    {
        $this->db->Execute("
            CREATE TABLE IF NOT EXISTS `aws_billing_records` (
              `record_id` VARCHAR(32) NOT NULL,
              `date` DATE NOT NULL,
              PRIMARY KEY (`record_id`),
              KEY `idx_date` (`date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ");
    }
}