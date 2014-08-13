<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140204143602 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '698c5619-cea3-45fd-811f-2468a00aa1c0';

    protected $depends = array('0f65a2c9-7592-4517-86d8-6789405ffaa2');

    protected $description = 'Refactor scripts table. Add new fields env_id, created_by, changed ... Add foreign key.';

    protected $accountsCache = array();

    protected $ignoreChanges = true;

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
        return !$this->hasTableColumn('scripts', 'origin');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('scripts');
    }

    protected function run1($stage)
    {
        $this->db->Execute('ALTER TABLE scripts DROP origin');
    }

    protected function isApplied2($stage)
    {
        return !$this->hasTableColumn('scripts', 'approval_state');
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('scripts');
    }

    protected function run2($stage)
    {
        $this->db->Execute('ALTER TABLE scripts DROP approval_state');
    }

    protected function isApplied3($stage)
    {
        return !$this->hasTable('script_revisions') || !$this->hasTableColumn('script_revisions', 'approval_state');
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTable('script_revisions');
    }

    protected function run3($stage)
    {
        $this->db->Execute('ALTER TABLE script_revisions DROP approval_state');
    }

    protected function isApplied4($stage)
    {
        return $this->hasTableColumn('scripts', 'created_by_id');
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTable('scripts');
    }

    protected function getUserInfoByAccountId($accountId)
    {
        if (! isset($this->accountsCache[$accountId])) {
            if ($accountId) {
                try {
                    $acc = new \Scalr_Account();
                    $acc->loadById($accountId);

                    $this->accountsCache[$accountId] = array(
                        'id' => $acc->getOwner()->id,
                        'email' => $acc->getOwner()->getEmail()
                    );
                } catch (\Exception $e) {
                    $this->console->error($e->getMessage());
                    return array('id' => 0, 'email' => '');
                }
            } else {
                $user = new \Scalr_Account_User();
                $user->loadByEmail('admin', 0);

                $this->accountsCache[$accountId] = array(
                    'id' => $user->id,
                    'email' => $user->getEmail()
                );
            }
        }

        return $this->accountsCache[$accountId];
    }

    protected function run4($stage)
    {
        $this->console->out('Creating fields');
        $this->db->Execute('ALTER TABLE scripts ADD `dtchanged` datetime DEFAULT NULL AFTER dtadded, ADD `created_by_id` int(11) DEFAULT NULL, ADD `created_by_email` varchar(250) DEFAULT NULL');
        $this->db->Execute('ALTER TABLE script_revisions ADD `changed_by_id` int(11) DEFAULT NULL, ADD `changed_by_email` varchar(250) DEFAULT NULL');
        $this->db->Execute('UPDATE scripts SET `dtchanged` = `dtadded`');

        $this->console->out('Look for old scripts');
        $ids = $this->db->GetCol('SELECT scripts.id FROM scripts LEFT JOIN clients ON scripts.clientid = clients.id WHERE clientid != 0 AND ISNULL(clients.id)');
        if (count($ids)) {
            $this->db->Execute('DELETE FROM scripts WHERE id IN (' . implode(',', $ids) . ')');
            $this->console->out('Clean ' . count($ids) . ' old scripts');
        }

        $ids = $this->db->GetCol('SELECT script_revisions.id FROM script_revisions LEFT JOIN scripts ON scripts.id = script_revisions.scriptid WHERE ISNULL(scripts.id)');
        if (count($ids)) {
            $this->db->Execute('DELETE FROM script_revisions WHERE id IN (' . implode(',', $ids) . ')');
            $this->console->out('Clean ' . count($ids) . ' old script revisions');
        }

        $this->console->out('Add foreign key for script_revisions');
        $this->db->Execute('ALTER TABLE `script_revisions` ADD CONSTRAINT `fk_script_revisions_scripts_id` FOREIGN KEY (`scriptid`) REFERENCES `scripts` (`id`) ON DELETE CASCADE');

        $this->console->out('Adding creator info');
        $scripts = $this->db->Execute('SELECT * FROM scripts');
        while ($script = $scripts->FetchRow()) {
            $info = $this->getUserInfoByAccountId($script['clientid']);
            $dtChanged = $this->db->GetOne('SELECT MAX(dtcreated) FROM script_revisions WHERE scriptid = ?', array($script['id']));

            $this->db->Execute('UPDATE scripts SET created_by_id = ?, created_by_email = ?, dtchanged = ? WHERE id = ?', array($info['id'], $info['email'], $dtChanged, $script['id']));
            $this->db->Execute('UPDATE script_revisions SET changed_by_id = ?, changed_by_email = ? WHERE scriptid = ?', array($info['id'], $info['email'], $script['id']));
        }
    }

    protected function isApplied5($stage)
    {
        return $this->hasTableColumn('scripts', 'env_id');
    }

    protected function validateBefore5($stage)
    {
        return $this->hasTable('scripts');
    }

    protected function run5($stage)
    {
        $this->db->Execute("ALTER TABLE scripts ADD `env_id` int(11) DEFAULT '0' AFTER clientid, ADD INDEX `clientid` (`clientid`), ADD INDEX `env_id` (`env_id`)");
    }
}
