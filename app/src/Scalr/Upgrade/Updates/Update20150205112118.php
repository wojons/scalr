<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Model\Entity;

class Update20150205112118 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '32f4a318-83ce-4f2e-934a-4d1101fd02a2';

    protected $depends = [];

    protected $description = 'Create and fill os_id for Role and Image objects';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 8;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableColumn('roles', 'os_id') &&
               !$this->getTableColumnDefinition('roles', 'os_id')->isNullable();
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('roles');
    }

    protected function run1($stage)
    {
        if (!$this->hasTableColumn('roles', 'os_id')) {
            $this->console->out("Adding os_id column to the roles table...");
            $this->db->Execute("ALTER TABLE `roles` ADD `os_id` VARCHAR(25) NOT NULL DEFAULT ''");
        }

        if ($this->getTableColumnDefinition('roles', 'os_id')->isNullable()) {
            //Repairs from the past inclomplete upgrade
            $this->console->out("Altering roles.os_id column as it should not be nullable...");
            $this->db->Execute("ALTER TABLE `roles` MODIFY `os_id` VARCHAR(25) NOT NULL DEFAULT ''");
        }
    }

    protected function isApplied2($stage)
    {
        return $this->hasTableColumn('images', 'os_id') &&
               !$this->getTableColumnDefinition('images', 'os_id')->isNullable();
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('images');
    }

    protected function run2($stage)
    {
        if (!$this->hasTableColumn('images', 'os_id')) {
            $this->console->out("Adding os_id column to the images table...");
            $this->db->Execute("ALTER TABLE `images` ADD `os_id` VARCHAR(25) NOT NULL DEFAULT ''");
        }

        if ($this->getTableColumnDefinition('images', 'os_id')->isNullable()) {
            $this->console->out("Altering images.os_id column as it should not be nullable...");
            $this->db->Execute("ALTER TABLE `images` MODIFY `os_id` VARCHAR(25) NOT NULL DEFAULT ''");
        }
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
        $this->console->out("Creating a new OS table...");
        $this->db->Execute("
            CREATE TABLE IF NOT EXISTS `os` (
              `id` varchar(25) NOT NULL,
              `name` varchar(50) NOT NULL,
              `family` varchar(20) NOT NULL,
              `generation` varchar(10) NOT NULL,
              `version` varchar(15) NOT NULL,
              `status` enum('active','inactive') DEFAULT 'inactive',
              `is_system` tinyint(1) DEFAULT '0',
              PRIMARY KEY (`id`),
              UNIQUE KEY `main` (`version`,`name`,`family`,`generation`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ");
    }

    protected function isApplied4($stage)
    {
        return false;
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTable('os');
    }

    protected function run4($stage)
    {
        $this->console->out("Initializing the OS table with the values...");
        $this->db->Execute("REPLACE INTO `os` (`id`, `name`, `family`, `generation`, `version`, `status`, `is_system`) VALUES
            ('amazon-2013-03', 'Amazon Linux 2013.03', 'amazon', '2013.03', '2013.03', 'active', 1),
            ('amazon-2014-03', 'Amazon Linux 2014.03', 'amazon', '2014.03', '2014.03', 'active', 1),
            ('amazon-2014-09', 'Amazon Linux 2014.09', 'amazon', '2014.09', '2014.09', 'active', 1),
            ('amazon-2015-03', 'Amazon Linux 2015.03', 'amazon', '2015.03', '2015.03', 'active', 1),
            ('centos-5-x', 'CentOS 5.X Final', 'centos', '5', '5.X', 'active', 1),
            ('centos-6-x', 'CentOS 6.X Final', 'centos', '6', '6.X', 'active', 1),
            ('centos-7-x', 'CentOS 7.X Final', 'centos', '7', '7.X', 'active', 1),
            ('debian-5-x', 'Debian 5.X Lenny', 'debian', '5', '5.X', 'active', 1),
            ('debian-6-x', 'Debian 6.X Squeeze', 'debian', '6', '6.X', 'active', 1),
            ('debian-7-x', 'Debian 7.X Wheezy', 'debian', '7', '7.X', 'active', 1),
            ('oracle-5-x', 'Oracle Enterprise Linux Server 5.X Tikanga', 'oel', '5', '5.X', 'active', 1),
            ('oracle-6-x', 'Oracle Enterprise Linux Server 6.X Santiago', 'oel', '6', '6.X', 'active', 1),
            ('redhat-5-x', 'Redhat 5.X Tikanga', 'redhat', '5', '5.X', 'active', 1),
            ('redhat-6-x', 'Redhat 6.X Santiago', 'redhat', '6', '6.X', 'active', 1),
            ('redhat-7-x', 'Redhat 7.X Maipo', 'redhat', '7', '7.X', 'active', 1),
            ('ubuntu-10-04', 'Ubuntu 10.04 Lucid', 'ubuntu', '10.04', '10.04', 'active', 1),
            ('ubuntu-10-10', 'Ubuntu 10.10 Maverick', 'ubuntu', '10.10', '10.10', 'active', 1),
            ('ubuntu-11-04', 'Ubuntu 11.04 Natty', 'ubuntu', '11.04', '11.04', 'active', 1),
            ('ubuntu-11-10', 'Ubuntu 11.10 Oneiric', 'ubuntu', '11.10', '11.10', 'active', 1),
            ('ubuntu-12-04', 'Ubuntu 12.04 Precise', 'ubuntu', '12.04', '12.04', 'active', 1),
            ('ubuntu-12-10', 'Ubuntu 12.10 Quantal', 'ubuntu', '12.10', '12.10', 'active', 1),
            ('ubuntu-13-04', 'Ubuntu 13.04 Raring', 'ubuntu', '13.04', '13.04', 'active', 1),
            ('ubuntu-13-10', 'Ubuntu 13.10 Saucy', 'ubuntu', '13.10', '13.10', 'active', 1),
            ('ubuntu-14-04', 'Ubuntu 14.04 Trusty', 'ubuntu', '14.04', '14.04', 'active', 1),
            ('ubuntu-14-10', 'Ubuntu 14.10 Utopic', 'ubuntu', '14.10', '14.10', 'active', 1),
            ('ubuntu-8-04', 'Ubuntu 8.04 Hardy', 'ubuntu', '8.04', '8.04', 'active', 1),
            ('windows-2003', 'Windows 2003', 'windows', '2003', '', 'active', 1),
            ('windows-2008', 'Windows 2008', 'windows', '2008', '', 'active', 1),
            ('windows-2012', 'Windows 2012', 'windows', '2012', '', 'active', 1),
            ('unknown-os', 'Unknown', 'unknown', 'unknown', 'unknown', 'active', 1)
        ");
    }

    protected function isApplied5($stage)
    {
        $count = $this->db->GetOne("SELECT COUNT(*) FROM roles WHERE os_id = ''");

        if ($count > 0) {
            $this->console->notice("Found {$count} roles without os_id. Updating...");
        }

        return !($count > 0);
    }

    protected function validateBefore5($stage)
    {
        return $this->hasTableColumn('roles', 'os_id');
    }

    protected function run5($stage)
    {
        $roles = $this->db->Execute("SELECT * FROM roles WHERE os_id = ''");

        while ($role = $roles->FetchRow()) {
            if ($role['os_version']) {
                $version = " AND (version = '{$role['os_version']}' OR version = '' OR version = '{$role['os_generation']}.X')";
            }

            $oses = $this->db->GetAll("SELECT id FROM os WHERE family = ? AND generation = ? {$version}", array(
                $role['os_family'], $role['os_generation']
            ));

            if (count($oses) == 1) {
                $this->db->Execute("UPDATE roles SET os_id = ? WHERE id = ?", array($oses[0]['id'], $role['id']));
            } elseif (count($oses) > 1) {
                $this->console->out("Multipe OSes found for Role #{$role['id']} ({$role['os_family']}, {$role['os_generation']}, {$role['os_version']})");
            }
        }

        $this->db->Execute("UPDATE roles SET os_id = 'unknown-os' WHERE os_generation IS NULL AND os_version IS NULL AND os_family IS NULL");
    }

    protected function isApplied6($stage)
    {
        $count = $this->db->GetOne("SELECT COUNT(*) FROM images WHERE os_id = ''");

        if ($count > 0) {
            $this->console->notice("Found {$count} images without os_id. Updating..");
        }

        return !($count > 0);
    }

    protected function validateBefore6($stage)
    {
        return $this->hasTableColumn('images', 'os_id');
    }

    protected function run6($stage)
    {
        $roles = $this->db->Execute("SELECT * FROM images WHERE os_id = ''");

        while ($image = $roles->FetchRow()) {
            if ($image['os_version']) {
                $version = " AND (version = '{$image['os_version']}' OR version = '' OR version = '{$image['os_generation']}.X')";
            }

            $oses = $this->db->GetAll("SELECT id FROM os WHERE family = ? AND generation = ? {$version}", array(
                $image['os_family'], $image['os_generation']
            ));

            if (count($oses) == 1) {
                $this->db->Execute("UPDATE images SET os_id = ? WHERE id = ?", array($oses[0]['id'], $image['id']));
            } elseif (count($oses) > 1) {
                $this->console->out("Multipe OSes found for Image #{$image['id']} ({$image['os_family']}, {$image['os_generation']}, {$image['os_version']})");
            }
        }

        $this->db->Execute("UPDATE images SET os_id = 'unknown-os' WHERE os_generation IS NULL AND os_version IS NULL AND os_family IS NULL");
    }

    protected function isApplied7($stage)
    {
        return $this->hasTableColumn('bundle_tasks', 'os_id') &&
               !$this->getTableColumnDefinition('bundle_tasks', 'os_id')->isNullable();
    }

    protected function validateBefore7($stage)
    {
        return $this->hasTable('bundle_tasks');
    }

    protected function run7($stage)
    {
        if (!$this->hasTableColumn('bundle_tasks', 'os_id')) {
            $action = 'ADD';
            $this->console->out("Adding os_id column to the bundle_tasks table...");
        } else if (!$this->getTableColumnDefinition('bundle_tasks', 'os_id')->isNullable()) {
            //Repairs from the past inclomplete upgrade
            $this->console->out("Altering bundle_tasks.os_id column as it should not be nullable...");
            $action = 'MODIFY';
        } else return;

        $this->db->Execute("ALTER TABLE `bundle_tasks` {$action} `os_id` VARCHAR(25) NOT NULL DEFAULT ''");
    }

    protected function isApplied8($stage)
    {
        return false;
    }

    protected function validateBefore8($stage)
    {
        return $this->hasTable('os');
    }

    protected function run8($stage)
    {
        $knownOses = [];

        //Retrieves the list of all known OSes
        foreach (Entity\Os::all() as $os) {
            /* @var $os Entity\Os */
            $knownOses[$os->id] = $os;
        }

        $role = new Entity\Role();

        //Trying to clarify the operating system of the Roles using Images which are associated with them.
        //If all Images have the same operating system it will be considered as acceptable for the Role at latter will be updated.
        $rs = $this->db->Execute("
            SELECT " . $role->fields('r', true) . ", GROUP_CONCAT(t.os_id) `osids`
            FROM roles r JOIN (
                SELECT DISTINCT ri.role_id, i.os_id
                FROM images i
                JOIN role_images ri ON i.id = ri.image_id
                    AND i.platform = ri.platform
                    AND i.cloud_location = ri.cloud_location
            ) t ON t.role_id = r.id
            WHERE r.os_id = ?
            GROUP BY r.id
            HAVING osids != r.os_id
        ", ['unknown-os']);

        if ($rs->RecordCount()) {
            $this->console->out("Found %d Roles the OS value of which can be filled from the Images. Updating...", $rs->RecordCount());
        }

        while ($row = $rs->FetchRow()) {
            $role = new Entity\Role();
            $role->load($row, 'r');

            if (!empty($row['osids'])) {
                if (isset($knownOses[$row['osids']])) {
                    //Updating OS value of the Role
                    $role->osId = $row['osids'];
                    $role->save();
                } else {
                    $this->console->warning(
                        "Role %s (%d) is associated with the Images with either different or unknown OS: %s",
                        $role->name, $role->id, $row['osids']
                    );
                }
            }
        }

        $image = new Entity\Image();

        //Trying to clarify the operating sytem of the Images using Roles which are associated with them.
        $rs = $this->db->Execute("
            SELECT " . $image->fields('i', true) . ", GROUP_CONCAT(t.os_id) `osids`
            FROM images i JOIN (
                SELECT DISTINCT ri.image_id, ri.platform, ri.cloud_location, r.os_id
                FROM roles r
                JOIN role_images ri ON ri.role_id = r.id
            ) t ON t.image_id = i.id AND t.platform = i.platform AND t.cloud_location = i.cloud_location
            WHERE i.os_id = ?
            GROUP BY i.hash
            HAVING osids != i.os_id
        ", ['unknown-os']);

        if ($rs->RecordCount()) {
            $this->console->out("Found %d Images the OS value of which can be filled from the Roles. Updating...", $rs->RecordCount());
        }

        while ($row = $rs->FetchRow()) {
            $image = new Entity\Image();
            $image->load($row, 'i');

            if (!empty($row['osids'])) {
                if (isset($knownOses[$row['osids']])) {
                    //Updating OS value of the Image
                    $image->osId = $row['osids'];
                    $image->save();
                } else {
                    $this->console->warning(
                        "Image (%s) imageId: %s, platform: %s, cloudLocation: %s is associated with the Roles with either different or unknown OS: %s",
                        $image->hash, $image->id, $image->platform, $image->cloudLocation, $row['osids']
                    );
                }
            }
        }
    }
}