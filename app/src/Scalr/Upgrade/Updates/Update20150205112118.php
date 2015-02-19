<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150205112118 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'd2276f63-0a39-47e7-a63b-820de9f514f2';

    protected $depends = [];

    protected $description = 'Create and fill os_id for Role and Image objects';

    protected $ignoreChanges = false;

    protected $dbservice = 'adodb';

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
        return $this->hasTableColumn('roles', 'os_id');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('roles');
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `roles` ADD `os_id` VARCHAR(25) NOT NULL DEFAULT '' ;");
    }
    
    protected function isApplied2($stage)
    {
        return $this->hasTableColumn('images', 'os_id');
    }
    
    protected function validateBefore2($stage)
    {
        return $this->hasTable('images');
    }
    
    protected function run2($stage)
    {
        $this->db->Execute("ALTER TABLE `images` ADD `os_id` VARCHAR(25) NOT NULL DEFAULT '' ;");
    }
    
    protected function isApplied3($stage)
    {
        return $this->hasTable('os');
    }
    
    protected function validateBefore3($stage)
    {
        return true;
    }
    
    protected function run3($stage)
    {
        $this->db->Execute("CREATE TABLE IF NOT EXISTS `os` (
          `id` varchar(25) NOT NULL,
          `name` varchar(50) NOT NULL,
          `family` varchar(20) NOT NULL,
          `generation` varchar(10) NOT NULL,
          `version` varchar(15) NOT NULL,
          `status` enum('active','inactive') DEFAULT 'inactive',
          `is_system` tinyint(1) DEFAULT '0'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        
        $this->db->Execute("ALTER TABLE `os` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `main` (`version`,`name`,`family`,`generation`)");
    }
    
    protected function isApplied5($stage)
    {
        $count = $this->db->GetOne("SELECT COUNT(*) FROM images WHERE os_id = ''");
        $this->console->notice("Found {$count} images without os_id. Updating..");
        return !($count > 0);
    }
    
    protected function validateBefore5($stage)
    {
        return $this->hasTableColumn('images', 'os_id');
    }
    
    protected function run5($stage)
    {
        $roles = $this->db->Execute("SELECT * FROM images WHERE os_id = ''");
        while ($image = $roles->FetchRow()) {
            if ($image['os_version'])
                $version = " AND (version = '{$image['os_version']}' OR version = '')";
    
            $oses = $this->db->GetAll("SELECT id FROM os WHERE family = ? AND generation = ? {$version}", array(
                $image['os_family'], $image['os_generation']
            ));
    
            if (count($oses) == 1)
                $this->db->Execute("UPDATE images SET os_id = ? WHERE id = ?", array($oses[0]['id'], $image['id']));
            elseif (count($oses) > 1) {
                $this->console->out("Multipe OSes found for Image #{$image['id']} ({$image['os_family']}, {$image['os_generation']}, {$image['os_version']})");
            }
        }
    }
    
    protected function isApplied4($stage)
    {
        $count = $this->db->GetOne("SELECT COUNT(*) FROM roles WHERE os_id = ''");
        $this->console->notice("Found {$count} roles without os_id. Updating..");
        return !($count > 0); 
    }
    
    protected function validateBefore4($stage)
    {
        return $this->hasTableColumn('roles', 'os_id');
    }
    
    protected function run4($stage)
    {
        $roles = $this->db->Execute("SELECT * FROM roles WHERE os_id = ''");
        while ($role = $roles->FetchRow()) {
            if ($role['os_version'])
                $version = " AND (version = '{$role['os_version']}' OR version = '')";
            
            $oses = $this->db->GetAll("SELECT id FROM os WHERE family = ? AND generation = ? {$version}", array(
                $role['os_family'], $role['os_generation']
            ));
            
            if (count($oses) == 1)
                $this->db->Execute("UPDATE roles SET os_id = ? WHERE id = ?", array($oses[0]['id'], $role['id']));
            elseif (count($oses) > 1) {
                $this->console->out("Multipe OSes found for Role #{$role['id']} ({$role['os_family']}, {$role['os_generation']}, {$role['os_version']})");
            }
        }
    }
}