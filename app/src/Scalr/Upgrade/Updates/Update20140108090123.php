<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140108090123 extends AbstractUpdate implements SequenceInterface
{

    private $dataToUpdate = array(
        array(
            'app-apache',
            'Blueprint for Apache instances. Use as a frontend or backend (paired with a Load Balancer role) web server.',
        ),
        array(
            'mysql',
            'Blueprint for MySQL database instances. Scalr will automatically assign master and slave if multiple instances are launched (Reassignment will be handled during scaling and failover).',
        ),
        array(
            'lb-nginx',
            'Blueprint for an Nginx load balancer/web server. It will proxy all requests to every instance of an Application Server role.',
        ),
    );

    protected $uuid = 'ae207d54-41f8-4840-99bf-8ee329cf37fa';

    protected $depends = array();

    protected $description = 'Changing description of some shared roles';

    protected $ignoreChanges = true;

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 3;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::validateBefore()
     */
    public function validateBefore($stage = null)
    {
        return $this->hasTable('roles') &&
               $this->hasTableColumn('roles', 'description') &&
               $this->hasTableColumn('roles', 'client_id');
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::isApplied()
     */
    public function isApplied($stage = null)
    {
        $rec = $this->dataToUpdate[$stage - 1];
        $found = $this->db->GetOne("
            SELECT description
            FROM roles
            WHERE client_id = 0 AND origin = 'SHARED'
            AND name LIKE '" . $rec[0] . "%' AND description <> ?
            LIMIT 1
        ", array(
            $rec[1]
        ));
        return $found === null || $found == $rec[1];
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::run()
     */
    public function run($stage = null)
    {
        $rec = $this->dataToUpdate[$stage - 1];

        $this->console->out('Updating for %s', $rec[0]);

        $this->db->Execute("
            UPDATE roles SET
                description = ?
            WHERE origin = 'SHARED'
            AND client_id = 0
            AND name LIKE '" . $rec[0] . "%'
        ", array(
            $rec[1]
        ));
    }
}