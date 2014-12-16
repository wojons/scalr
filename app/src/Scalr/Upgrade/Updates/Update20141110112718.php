<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20141110112718 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'f624e155-3e44-4589-8278-581e5f91a29a';

    protected $depends = [];

    protected $description = 'Refactor custom_events, add foreign keys';

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
        return $this->hasTableForeignKey('fk_event_defs_client_envs_id', 'event_definitions');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('event_definitions');
    }

    protected function run1($stage)
    {
        $this->console->out('Fill account_id');

        foreach ($this->db->GetAll('SELECT e.*, ce.client_id FROM event_definitions e LEFT JOIN client_environments ce ON ce.id = e.env_id') as $ed) {
            if (is_null($ed['client_id'])) {
                $this->db->Execute('DELETE FROM event_definitions WHERE id = ?', [$ed['id']]);
            } else {
                $this->db->Execute('UPDATE event_definitions SET account_id = ? WHERE id = ?', [$ed['client_id'], $ed['id']]);
            }
        }

        $this->console->out('Make foreign keys');
        $this->db->Execute('ALTER TABLE event_definitions MODIFY account_id int(11) DEFAULT NULL, MODIFY env_id int(11) DEFAULT NULL');
        $this->db->Execute('ALTER TABLE event_definitions ADD CONSTRAINT `fk_event_defs_client_envs_id` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION');
        $this->db->Execute('ALTER TABLE event_definitions ADD CONSTRAINT `fk_event_defs_clients_id` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION');
    }
}