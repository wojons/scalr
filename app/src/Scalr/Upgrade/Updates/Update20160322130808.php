<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Acl\Acl;
use Scalr\Acl\Resource\Definition;
use Scalr\Model\Entity\Account\User;
use Scalr\Model\Entity\Account\User\UserSetting;

/**
 * Class Update20160322130808
 *
 * SCALRCORE-2652 Account Announcement Functionality
 *  - stage1: DDL create table `announcements`
 *  - stage2: DML add ACL resource RESOURCE_ANNOUNCEMENTS
 *  - stage3: DML update saved user settings:
 *            rename 'ui.changelog.time' to 'ui.announcement.time'
 *
 * @namespace Scalr\Upgrade\Updates
 */
class Update20160322130808 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '58378b93-e7b6-4f0e-81f6-46baba2c927c';

    protected $depends = [];

    protected $description = 'Adding Account Announcement Functionality';

    protected $dbservice = 'adodb';

    protected $tableAnnouncements = 'announcements';

    protected $oldSettingName     = 'ui.changelog.time';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 3;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable($this->tableAnnouncements);
    }

    protected function validateBefore1($stage)
    {
        return !$this->hasTable($this->tableAnnouncements);
    }

    protected function run1($stage)
    {
        $this->console->out('Creating table for announcements...');

        $this->db->Execute("
            CREATE TABLE `{$this->tableAnnouncements}` (
                `id` int NOT NULL AUTO_INCREMENT,
                `account_id` int DEFAULT NULL COMMENT 'Identifier of Account',
                `created_by_id` int DEFAULT NULL COMMENT 'User who created an announcement',
                `created_by_email` varchar(100) NOT NULL COMMENT 'Email of the User who created an announcement',
                `added` datetime NOT NULL COMMENT 'Create timestamp',
                `title` varchar(100) NOT NULL,
                `msg` text NOT NULL COMMENT 'Raw content',
                PRIMARY KEY (`id`),
                KEY `idx_added` (`added`),
                CONSTRAINT `fk_12741abab84d`
                    FOREIGN KEY (`created_by_id`) REFERENCES `account_users` (`id`)
                    ON DELETE SET NULL,
                CONSTRAINT `fk_312de420e119`
                    FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT 'User Announcements'
        ");
    }

    protected function isApplied2($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_ANNOUNCEMENTS') &&
            $this->db->GetOne("
               SELECT `granted` FROM `acl_role_resources`
               WHERE `role_id` = ? AND `resource_id` = ?
               LIMIT 1
            ", [Acl::ROLE_ID_FULL_ACCESS, Acl::RESOURCE_ANNOUNCEMENTS]
        ) == 1;
    }

    protected function validateBefore2($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_ANNOUNCEMENTS') &&
            Definition::has(Acl::RESOURCE_ANNOUNCEMENTS);
    }

    protected function run2($stage)
    {
        $this->console->out('Adding ACL resource to manage announcements...');

        $this->db->Execute("
            INSERT IGNORE INTO `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1)
        ", [
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_ANNOUNCEMENTS
        ]);
    }

    protected function isApplied3($stage)
    {
        return $this->db->GetOne("
               SELECT 1 FROM `account_user_settings`
               WHERE `name` = ?
               LIMIT 1
          ", [$this->oldSettingName]
        ) != 1;
    }

    protected function validateBefore3($stage)
    {
        return defined('Scalr\\Model\\Entity\\Account\\User\\UserSetting::NAME_UI_ANNOUNCEMENT_TIME') &&
            !defined('Scalr\\Model\\Entity\\Account\\User\\UserSetting::SETTING_UI_CHANGELOG_TIME') &&
            !defined('Scalr_Account_User::SETTING_UI_CHANGELOG_TIME');
    }

    protected function run3($stage)
    {
        $this->console->out('Updating `account_user_settings`...');

        $this->db->Execute(
            'UPDATE `account_user_settings` SET `name` = ? WHERE `name` = ?',
            [UserSetting::NAME_UI_ANNOUNCEMENT_TIME, $this->oldSettingName]
        );
        $affected = $this->db->Affected_Rows();

        $this->console->out("Updated {$affected} setting(s).");
    }
}
