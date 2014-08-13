<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140702094052 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'c908b8da-0481-49a0-9fe7-6fc65e408f6b';

    protected $depends = ['2b336d25-d327-4c00-aaa2-e990aa8df7b8'];

    protected $description = 'Create new table tags';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    public function getNumberStages()
    {
        return 3;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('tags');
    }

    protected function run1($stage)
    {
        $this->db->Execute("CREATE TABLE `tags` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `name` varchar(255) NOT NULL DEFAULT '',
              PRIMARY KEY (`id`),
              UNIQUE KEY `idx_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
    }

    protected function isApplied2()
    {
        return $this->hasTable('tag_link');
    }

    protected function run2()
    {
        $this->db->Execute("
        CREATE TABLE `tag_link` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `tag_id` int(11) NOT NULL,
              `resource` varchar(32) NOT NULL,
              `resource_id` int(11) NOT NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `fk_tag_link_tags_id` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
    }

    protected function run3()
    {
        $this->db->Execute('DELETE FROM tags WHERE `name` = ?', ['[]']);
    }
}
