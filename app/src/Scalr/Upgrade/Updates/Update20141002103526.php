<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Stats\CostAnalytics\Entity\TimelineEventEntity;
use Exception;

class Update20141002103526 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '3be128da-87a1-4201-8ee3-9c64456db4c8';

    protected $depends = [];

    protected $description = 'Update timeline_events table and fill it with account_id and env_id';

    protected $ignoreChanges = true;

    protected $dbservice = 'cadb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::isRefused()
     */
    public function isRefused()
    {
        return !$this->container->analytics->enabled ? "Cost analytics is turned off" : false;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('timeline_events') && $this->hasTableColumn('timeline_events', 'env_id');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('timeline_events');
    }

    protected function run1($stage)
    {
        $this->db->Execute('
            ALTER TABLE `timeline_events`
                ADD `account_id` int(11) NULL AFTER `user_id`,
                ADD `env_id` int(11) NULL AFTER `account_id`,
                ADD INDEX `idx_account_id` (`account_id` ASC),
                ADD INDEX `idx_env_id` (`env_id` ASC)
        ');

        $res = $this->db->Execute('SELECT * FROM timeline_events');

        while ($item = $res->FetchRow()) {
            $event = new TimelineEventEntity();
            $event->load($item);

            try {
                $event->accountId = \Scalr_Account_User::init()->loadById($event->userId)->getAccountId();
            } catch (Exception $e) {
                continue;
            }

            $event->save();
        }
    }
}