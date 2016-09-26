<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Stats\CostAnalytics\Entity\NotificationEntity;

class Update20141024072918 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '03504da8-ca9b-4537-9efd-315fe869321a';

    protected $depends = [];

    protected $description = 'Analytics notifications/reports structure change';

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
        return 5;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableColumn('notifications', 'subject_id');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('notifications');
    }

    protected function run1($stage)
    {
        $this->console->out("Adding 'subject_id' to the notifications table...");
        $this->db->Execute("ALTER TABLE `notifications` ADD COLUMN `subject_id` BINARY(16) NULL DEFAULT NULL AFTER `subject_type`");
    }

    protected function isApplied2($stage)
    {
        return $this->hasTableColumn('notifications', 'status');
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('notifications');
    }

    protected function run2($stage)
    {
        $this->console->out("Adding 'status' to the notifications table...");
        $this->db->Execute("ALTER TABLE `notifications` ADD COLUMN `status` TINYINT(4) NOT NULL  AFTER `emails`");
    }

    protected function isApplied3($stage)
    {
        return $this->hasTableColumn('reports', 'status');
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTable('reports');
    }

    protected function run3($stage)
    {
        $this->console->out("Adding 'status' to the reports table...");
        $this->db->Execute("ALTER TABLE `reports` ADD COLUMN `status` TINYINT(4) NOT NULL  AFTER `emails`");
    }

    protected function isApplied4($stage)
    {
        return false;
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTable('notifications');
    }

    protected function run4($stage)
    {
        $this->console->out("Updating notifications status...");
        $this->db->Execute("UPDATE `notifications` set `status` = ? WHERE `subject_type` = ?", array(
            (int)SettingEntity::getValue(SettingEntity::ID_NOTIFICATIONS_CCS_ENABLED),
            NotificationEntity::SUBJECT_TYPE_CC
        ));
        $this->db->Execute("UPDATE `notifications` set `status` = ? WHERE `subject_type` = ?", array(
            (int)SettingEntity::getValue(SettingEntity::ID_NOTIFICATIONS_PROJECTS_ENABLED),
            NotificationEntity::SUBJECT_TYPE_PROJECT
        ));
    }

    protected function isApplied5($stage)
    {
        return false;
    }

    protected function validateBefore5($stage)
    {
        return $this->hasTable('reports');
    }

    protected function run5($stage)
    {
        $this->console->out("Updating reports status...");
        $this->db->Execute("UPDATE `reports` set `status` = ?", array((int)SettingEntity::getValue(SettingEntity::ID_REPORTS_ENABLED)));
    }

}