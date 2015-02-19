<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20141219133513 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '787ab78a-e13a-4532-ad8e-692008014d96';

    protected $depends = [];

    protected $description = 'Create account_ccs table';

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
        return $this->hasTable('account_ccs');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->console->out("Creating account_ccs table...");

        $this->db->Execute("
            CREATE TABLE IF NOT EXISTS `account_ccs` (
              `account_id` int(11) NOT NULL COMMENT 'clients.id reference',
              `cc_id` binary(16) NOT NULL COMMENT 'ccs.cc_id reference',
              PRIMARY KEY (`account_id`, `cc_id`),
              INDEX `idx_cc_id` (`cc_id` ASC),
              CONSTRAINT `fk_bd44f317af5fc8ab`
                FOREIGN KEY (`account_id`)
                REFERENCES `clients` (`id`)
                ON DELETE CASCADE
                ON UPDATE NO ACTION,
              CONSTRAINT `fk_b6e70905def505d6`
                FOREIGN KEY (`cc_id`)
                REFERENCES `ccs` (`cc_id`)
                ON DELETE CASCADE
                ON UPDATE NO ACTION
            ) ENGINE = InnoDB
        ");
    }
}