<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Model\Entity\Account\User\ApiKeyEntity;

class Update20150217125922 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '2f18fa57-70ee-4319-bbd0-dc02aad3ddb6';

    protected $depends = [];

    protected $description = 'Creates account_user_apikeys table';

    protected $ignoreChanges = false;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 2;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('account_user_apikeys');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('account_users');
    }

    protected function run1($stage)
    {
        $this->console->out("Creating new account_user_apikeys table...");
        $this->db->Execute("
            CREATE TABLE IF NOT EXISTS `account_user_apikeys` (
              `key_id` CHAR(20) NOT NULL COMMENT 'The unique identifier of the key',
              `user_id` INT(11) NOT NULL COMMENT 'scalr.account_users.id ref',
              `secret_key` VARCHAR(255) NOT NULL COMMENT 'Encrypted secret key',
              `active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 - active, 0 - inactive',
              PRIMARY KEY (`key_id`),
              INDEX `idx_user_keys` (`user_id`, `active`),
              INDEX `idx_active` (`active`),
              CONSTRAINT `fk_0a036c6a9223`
                FOREIGN KEY (`user_id`)
                REFERENCES `account_users` (`id`)
                ON DELETE CASCADE
                ON UPDATE RESTRICT
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8 COMMENT = 'API keys'
        ");
    }

    protected function run2($stage)
    {
        $hasone = false;

        //Selects only those users who do not have any key yet
        $rs = $this->db->Execute("
            SELECT u.id FROM `account_users` u
            LEFT JOIN `account_user_apikeys` k ON k.user_id = u.id
            WHERE k.user_id IS NULL
        ");

        while ($rec = $rs->FetchRow()) {
            if (!$hasone) {
                $this->console->out("Initializing API keys for all users who do not have one...");
                $hasone = true;
            }

            try {
                $apiKey = new ApiKeyEntity($rec['id']);
                $apiKey->save();
            } catch (\Exception $e) {
                continue;
            }
        }
    }
}