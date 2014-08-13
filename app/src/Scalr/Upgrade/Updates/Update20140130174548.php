<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

/**
 * Adding foreign key to farm_settings on delete cascade
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    4.5.2 (30.01.2014)
 */
class Update20140130174548 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '97da20e1-cc52-4015-aad7-f3534542e5e3';

    protected $depends = array(
        '7ec5da23-3311-4ab4-a3c4-d3101759ef16'
    );

    protected $description = 'Adding foreign key to farm_settings on delete cascade';

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
        return $this->hasTableForeignKey('fk_farm_settings_farms', 'farm_settings');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('farm_settings') && $this->hasTable('farms');
    }

    protected function run1($stage)
    {
        $this->db->BeginTrans();
        $this->db->Execute("
            DELETE FROM farm_settings
            WHERE NOT EXISTS (
                SELECT 1 FROM farms
                WHERE farms.id = farm_settings.farmid
            )
        ");
        $this->db->Execute("
            ALTER TABLE `farm_settings` ADD CONSTRAINT `fk_farm_settings_farms`
            FOREIGN KEY (`farmid`) REFERENCES `farms` (`id`) ON DELETE CASCADE
        ");
        $this->db->CommitTrans();
    }
}