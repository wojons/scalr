<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140812134140 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'e9e2ff6c-4829-40a5-94e7-2a6f54cb06d8';

    protected $depends = ['3390da80-4daa-4f94-ae89-a4cd4b926835'];

    protected $description = 'Add missing accountId property to tags';

    protected $ignoreChanges = false;

    protected $dbservice = 'adodb';

    public function getNumberStages()
    {
        return 4;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableColumn('tags', 'account_id');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('tags');
    }

    protected function run1($stage)
    {
        $this->db->Execute('ALTER TABLE tags DROP KEY idx_name,
            ADD `account_id` int(11) NULL,
            ADD UNIQUE KEY `idx_name` (`name`, `account_id`),
            ADD CONSTRAINT `fk_tags_clients_id` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
        ');

        $tlinks = $this->db->GetAll('SELECT tl.*, s.account_id FROM tag_link tl LEFT JOIN scripts s ON s.id = tl.resource_id');
        foreach ($tlinks as $l) {
            $tag = $this->db->GetRow('SELECT * FROM tags WHERE id = ?', [$l['tag_id']]);
            if ($tag['account_id']) {
                if ($tag['account_id'] != $l['account_id']) {
                    // duplicate tag, it's used in different accounts
                    $check = $this->db->GetRow('SELECT * FROM tags WHERE name = ? AND account_id = ?', [ $tag['name'], $l['account_id'] ]);
                    if (!$check) {
                        $this->db->Execute('INSERT INTO tags (`name`, `account_id`) VALUES(?,?)', [ $tag['name'], $l['account_id'] ]);
                        $check['id'] = $this->db->Insert_ID();
                    }

                    $this->db->Execute('UPDATE tag_link SET tag_id = ? WHERE id = ?', [$check['id'], $l['id']]);
                }
            } else {
                $this->db->Execute('UPDATE tags SET account_id = ? WHERE id = ?', [$l['account_id'], $l['tag_id']]);
            }
        }
    }

    protected function isApplied2()
    {
        return !$this->hasTableColumn('tag_link', 'id');
    }

    protected function validateBefore2()
    {
        return $this->hasTable('tag_link');
    }

    protected function run2()
    {
        $this->db->Execute('ALTER TABLE tag_link DROP id, ADD UNIQUE KEY `idx_id` (`tag_id`,`resource`,`resource_id`)');
    }

    protected function isApplied3()
    {
        return $this->hasTableForeignKey('fk_tag_link_tags_id', 'tag_link');
    }

    protected function validateBefore3()
    {
        return $this->hasTable('tag_link');
    }

    protected function run3()
    {
        $this->db->Execute('ALTER TABLE tag_link ADD CONSTRAINT `fk_tag_link_tags_id` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE');
    }

    protected function isApplied4()
    {
        return $this->hasTableIndex('tag_link', 'idx_resource');
    }

    protected function validateBefore4()
    {
        return $this->hasTable('tag_link');
    }

    protected function run4()
    {
        $this->db->Execute('ALTER TABLE tag_link ADD INDEX `idx_resource` (`resource`, `resource_id`)');
    }
}
