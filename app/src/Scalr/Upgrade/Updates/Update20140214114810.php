<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140214114810 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = 'e1627971-131f-4fb3-900b-2a84a0042411';

    protected $depends = array();

    protected $description = 'Adds category field to the governance table';

    protected $ignoreChanges = true;

    protected $categories = array(
        \SERVER_PLATFORMS::EC2 => 'aws',
        \SERVER_PLATFORMS::IDCF => \SERVER_PLATFORMS::IDCF,
        \SERVER_PLATFORMS::CLOUDSTACK => \SERVER_PLATFORMS::CLOUDSTACK,
        \SERVER_PLATFORMS::OPENSTACK => \SERVER_PLATFORMS::OPENSTACK,
        \SERVER_PLATFORMS::ECS => \SERVER_PLATFORMS::ECS,
        'general' => 'general'
    );

    public function getNumberStages()
    {
        return 3;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableColumn('governance', 'category');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->db->Execute('ALTER TABLE `governance` ADD COLUMN `category` VARCHAR(20) NOT NULL AFTER `env_id`');
    }

    protected function isApplied2($stage)
    {
        return count($this->db->GetAll("SHOW KEYS FROM governance WHERE Key_name = 'PRIMARY'")) == 3;
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTableColumn('governance', 'category');
    }

    protected function run2($stage)
    {
        $this->db->Execute("ALTER TABLE `governance` DROP PRIMARY KEY, ADD PRIMARY KEY (`env_id`, `category`, `name`)");
    }

    protected function isApplied3($stage)
    {
        if ($this->db->GetOne("SELECT 1 FROM governance WHERE category = '' LIMIT 1")) {
            return false;
        }
        if ($this->db->GetOne("SELECT 1 FROM governance WHERE name LIKE " . $this->db->qstr(\SERVER_PLATFORMS::IDCF . '.%') . " OR name LIKE " . $this->db->qstr(\SERVER_PLATFORMS::ECS . '.%') . " LIMIT 1")) {
            return false;
        }

        return true;
    }

    protected function validateBefore3($stage)
    {
        return count($this->db->GetAll("SHOW KEYS FROM governance WHERE Key_name = 'PRIMARY'")) == 3;
    }

    protected function run3($stage)
    {
        foreach ($this->categories as $platform => $prefix) {
            $this->db->Execute("UPDATE `governance` SET category = " . $this->db->qstr($platform) . " WHERE name LIKE " . $this->db->qstr($prefix . '.%'));
        }

        $this->db->Execute("
            UPDATE `governance`
            SET name = REPLACE(name, " . $this->db->qstr(\SERVER_PLATFORMS::IDCF) . ", " . $this->db->qstr(\SERVER_PLATFORMS::CLOUDSTACK) . ")
            WHERE name LIKE " . $this->db->qstr(\SERVER_PLATFORMS::IDCF . '.%')
        );

        $this->db->Execute("
            UPDATE `governance`
            SET name = REPLACE(name, " . $this->db->qstr(\SERVER_PLATFORMS::ECS) . ", " . $this->db->qstr(\SERVER_PLATFORMS::OPENSTACK) . ")
            WHERE name LIKE " . $this->db->qstr(\SERVER_PLATFORMS::ECS . '.%')
        );
    }

}