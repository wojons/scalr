<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Exception;

class Update20160309142153 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'fb2f138c-daf5-4e3a-8caf-5181e277631c';

    protected $depends = [];

    protected $description = 'Adds functionality: Farm can be owned by many Teams';

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
        return $this->hasTable('farm_teams');
    }

    protected function run1($stage)
    {
        $this->console->out("Creating farm_teams table...");
        
        $this->db->Execute("
            CREATE TABLE `farm_teams` (
                `farm_id` INT(11) NOT NULL COMMENT 'farms.id ref',
                `team_id` INT(11) NOT NULL COMMENT 'account_teams.id ref',
                PRIMARY KEY (`farm_id`,`team_id`),
                KEY `idx_team_id` (`team_id`),
                CONSTRAINT `fk_8e10336984d4` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
                CONSTRAINT `fk_91800ad81855` FOREIGN KEY (`team_id`) REFERENCES `account_teams` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8
        ");

        $this->db->BeginTrans();
        
        try {
            $this->console->out("Copying existing associations between Farms and Teams to newly created table...");
            
            $this->db->Execute("
                INSERT INTO `farm_teams` (`farm_id`, `team_id`)
                SELECT `id`, `team_id` 
                FROM `farms` 
                WHERE team_id IS NOT NULL
            ");
            
            $this->console->out("Removing obsolete 'team_id' column and 'farms_account_teams_id' key...");
            
            $this->db->Execute("ALTER TABLE `farms` DROP FOREIGN KEY `farms_account_teams_id`");
            $this->db->Execute("ALTER TABLE `farms` DROP COLUMN `team_id`");
            
            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->console->error("Something went wrong!");
            
            $this->db->RollbackTrans();
            
            throw $e;
        }
    }
}
