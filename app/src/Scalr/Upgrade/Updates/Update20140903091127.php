<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140903091127 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '034462c2-1073-466b-ac64-b53bad561785';

    protected $depends = ['e163fffa-1f19-4e67-b761-5f6c100bbb04'];

    protected $description = 'Create table role_image_history';

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
        return $this->hasTable('role_image_history');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->db->Execute("CREATE TABLE `role_image_history` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `role_id` int(11) NOT NULL,
              `platform` varchar(25) NOT NULL,
              `cloud_location` varchar(36) NOT NULL,
              `image_id` varchar(255) NOT NULL DEFAULT '',
              `old_image_id` varchar(255) NOT NULL DEFAULT '',
              `dt_added` datetime NOT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_role_id` (`role_id`),
              CONSTRAINT `fk_role_image_history_roles_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
            ) ENGINE=InnoDB
        ");
    }
}