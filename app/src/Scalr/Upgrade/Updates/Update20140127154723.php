<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\TagEntity;

/**
 * Initializing analytics.tags
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @version  5.0 (27.01.2014)
 */
class Update20140127154723 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '2702d6db-faff-4fb2-8649-e90e4e700778';

    protected $depends = ['22dd3ef7-9431-4d27-bf23-07d7deb00777'];

    protected $description = 'Initializing analytics.tags';

    protected $dbservice = 'cadb';

    protected $ignoreChanges = true;

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::isRefused()
     */
    public function isRefused()
    {
        return !$this->container->analytics->enabled ? "Cost analytics is turned off" : false;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 9;
    }

    /**
     * Gets the number of the records for the specified tag
     * in the analytics.account_tag_values table
     *
     * @param   int    $tagId  The identifier of the tag
     * @return  int    Returns the count of the records in the analytics.account_tag_values table
     */
    private function getCountOfAccountTagValues($tagId = null)
    {
        return $this->db->GetOne("
            SELECT COUNT(*) cnt
            FROM account_tag_values
            WHERE " . (is_null($tagId) ? "1" : "tag_id = " . $this->db->escape($tagId)) . "
        ");
    }

    /**
     * Validates environment for specified parameters
     *
     * @param   int     $tagId
     * @param   string  $tagName
     * @return  boolean Returns true on success or false otherwise
     */
    private function validateCommon($tagId, $tagName)
    {
        $dbname = $this->container->config('scalr.analytics.connections.analytics.name');

        if (!$this->hasTable('account_tag_values')) {
            $this->console->error("Table %d does not exist.", $dbname . '.account_tag_values');
            return false;
        }

        if (!$this->hasTable('tags')) {
            $this->console->error("Table %d does not exist.", $dbname . '.tags');
            return false;
        }

        $r = $this->db->GetOne("
            SELECT 1 FROM tags
            WHERE tag_id = ? LIMIT 1
        ", array(
            $tagId
        ));

        if (!$r) {
            $this->console->error("Tag %s (%d) does not exist in the %s table.", $tagName, $tagId, $dbname . '.tags');
            return false;
        }

        return true;
    }

    /**
     * Checks if applied for specified tag
     *
     * @param   int     $tagId  The ID of the tag
     * @return  boolean Returns true if it's already been applied.
     */
    private function isAppliedCommon($tagId)
    {
        return false;
    }

    protected function isApplied1($stage)
    {
        return $this->isAppliedCommon(TagEntity::TAG_ID_ENVIRONMENT);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::hasTable()
     */
    public function hasTableService($table, $service = 'adodb')
    {
        $ret = $this->container->$service->getOne("
            SHOW TABLES LIKE ?
        ", array(
            $table
        ));
        return $ret ? true : false;
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTableService('client_environments') && $this->validateCommon(TagEntity::TAG_ID_ENVIRONMENT, 'Environment');
    }

    private function getScalrDatabaseName()
    {
        return $this->container->config('scalr.connections.mysql.name');
    }

    protected function run1($stage)
    {
        if ($this->getCountOfAccountTagValues() &&
            $this->console->confirm('Would you like to clean-up account_tag_values table?')) {
            $this->db->Execute("DELETE FROM `account_tag_values`");
        }
        if ($this->getCountOfAccountTagValues(TagEntity::TAG_ID_ENVIRONMENT) &&
            $this->console->confirm('Would you like to remove old environments from account_tag_values?')) {
            $this->console->out("Removing old environments");
            $this->db->Execute("DELETE FROM `account_tag_values` WHERE tag_id = ?", array(
                TagEntity::TAG_ID_ENVIRONMENT
            ));
        }
        $this->console->out('Populating environments to the dictionary');

        $db = \Scalr::getDb();

        $stmt = '';

        //Retrieves data from scalr database
        $res = $db->Execute("
            SELECT
                c.`client_id` `account_id`,
                ? `tag_id`,
                c.`id` `value_id`,
                c.`name` `value_name`
            FROM `client_environments` c
        ", [TagEntity::TAG_ID_ENVIRONMENT]);

        while ($rec = $res->FetchRow()) {
            $stmt .= ',('
                  . $db->qstr($rec['account_id']) . ', '
                  . $db->qstr($rec['tag_id']) . ', '
                  . $db->qstr($rec['value_id']) . ', '
                  . $db->qstr($rec['value_name']) . ')'
            ;
        }

        if ($stmt != '') {
            //Inserts data to analytics database
            $this->db->Execute("
                INSERT IGNORE `account_tag_values` (`account_id`, `tag_id`, `value_id`, `value_name`)
                VALUES " . substr($stmt, 1) . "
            ");
        }
    }

    protected function isApplied2($stage)
    {
        return $this->isAppliedCommon(TagEntity::TAG_ID_PLATFORM);
    }

    protected function validateBefore2($stage)
    {
        return $this->validateCommon(TagEntity::TAG_ID_PLATFORM, 'Platform');
    }

    protected function run2($stage)
    {
        if ($this->getCountOfAccountTagValues(TagEntity::TAG_ID_PLATFORM) &&
            $this->console->confirm('Would you like to remove old platforms from account_tag_values?')) {
            $this->console->out("Removing old platforms");
            $this->db->Execute("DELETE FROM account_tag_values WHERE tag_id = ?", array(
                TagEntity::TAG_ID_PLATFORM
            ));
        }
        $this->console->out('Populating platforms to the dictionary');

        $platforms = \SERVER_PLATFORMS::GetList();
        $pars = array();
        $values = '';
        foreach ($platforms as $id => $name) {
            $values .= ",('0', '" . TagEntity::TAG_ID_PLATFORM. "', ?, ?)";
            $pars[] = $id;
            $pars[] = $name;
        }
        $values = ltrim($values, ',');

        if ($values != '') {
            $this->db->Execute("
                INSERT IGNORE account_tag_values (`account_id`, `tag_id`, `value_id`, `value_name`)
                VALUES " . $values . "
            ", $pars);
        }
    }

    protected function isApplied3($stage)
    {
        return true;
    }

    protected function validateBefore3($stage)
    {
        return true;
    }

    protected function run3($stage)
    {
        //3 tag (Team) is rejected
    }

    protected function isApplied4($stage)
    {
        return $this->isAppliedCommon(TagEntity::TAG_ID_FARM) &&
               $this->isAppliedCommon(TagEntity::TAG_ID_FARM_OWNER);
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTableService('farms') &&
               $this->validateCommon(TagEntity::TAG_ID_FARM, 'Farm') &&
               $this->validateCommon(TagEntity::TAG_ID_FARM_OWNER, 'Farm owner');
    }

    protected function run4($stage)
    {
        if ($this->getCountOfAccountTagValues(TagEntity::TAG_ID_FARM) &&
            $this->console->confirm('Would you like to remove old farms from account_tag_values?')) {
            $this->console->out("Removing old farms");
            $this->db->Execute("DELETE FROM account_tag_values WHERE tag_id = ?", array(
                TagEntity::TAG_ID_FARM
            ));
        }

        $this->console->out('Populating farms to the dictionary');

        $db = \Scalr::getDb();

        $stmt = '';

        //Retrieves data from scalr database
        $res = $db->Execute("
            SELECT
                f.`clientid` `account_id`,
                ? `tag_id`,
                f.`id` `value_id`,
                f.`name` `value_name`
            FROM `farms` f

            UNION ALL

            SELECT
                f.`clientid` `account_id`,
                ? `tag_id`,
                f.`id` `value_id`,
                f.`created_by_id` `value_name`
            FROM `farms` f
            WHERE f.`created_by_id` > 0
        ", [
            TagEntity::TAG_ID_FARM,
            TagEntity::TAG_ID_FARM_OWNER
        ]);

        while ($rec = $res->FetchRow()) {
            $stmt .= ',('
                  . $db->qstr($rec['account_id']) . ', '
                  . $db->qstr($rec['tag_id']) . ', '
                  . $db->qstr($rec['value_id']) . ', '
                  . $db->qstr($rec['value_name']) . ')'
            ;
        }

        if ($stmt != '') {
            //Inserts data to analytics database
            $this->db->Execute("
                INSERT IGNORE `account_tag_values` (`account_id`, `tag_id`, `value_id`, `value_name`)
                VALUES " . substr($stmt, 1) . "
            ");
        }
    }

    protected function isApplied5($stage)
    {
        return $this->isAppliedCommon(TagEntity::TAG_ID_FARM_ROLE);
    }

    protected function validateBefore5($stage)
    {
        return $this->hasTableService('farm_roles') && $this->validateCommon(TagEntity::TAG_ID_FARM_ROLE, 'Farm role');
    }

    protected function run5($stage)
    {
        if ($this->getCountOfAccountTagValues(TagEntity::TAG_ID_FARM_ROLE) &&
            $this->console->confirm('Would you like to remove old farm roles from account_tag_values?')) {
            $this->console->out("Removing old farm roles");
            $this->db->Execute("DELETE FROM account_tag_values WHERE tag_id = ?", array(
                TagEntity::TAG_ID_FARM_ROLE
            ));
        }
        $this->console->out('Populating farm roles to the dictionary');

        $db = \Scalr::getDb();

        $stmt = '';

        //Retrieves data from scalr database
        $res = $db->Execute("
            SELECT
                f.`clientid` `account_id`,
                ? `tag_id`,
                fr.`id` `value_id`,
                fr.`alias` `value_name`
            FROM `farm_roles` fr
            JOIN `farms` f ON f.id = fr.farmid
        ", [TagEntity::TAG_ID_FARM_ROLE]);

        while ($rec = $res->FetchRow()) {
            $stmt .= ',('
                  . $db->qstr($rec['account_id']) . ', '
                  . $db->qstr($rec['tag_id']) . ', '
                  . $db->qstr($rec['value_id']) . ', '
                  . $db->qstr($rec['value_name']) . ')'
            ;
        }

        if ($stmt != '') {
            //Inserts data to analytics database
            $this->db->Execute("
                INSERT IGNORE `account_tag_values` (`account_id`, `tag_id`, `value_id`, `value_name`)
                VALUES " . substr($stmt, 1) . "
            ");
        }
    }

    protected function isApplied6($stage)
    {
        return $this->isAppliedCommon(TagEntity::TAG_ID_USER);
    }

    protected function validateBefore6($stage)
    {
        return $this->hasTableService('account_users') && $this->validateCommon(TagEntity::TAG_ID_USER, 'User');
    }

    protected function run6($stage)
    {
        if ($this->getCountOfAccountTagValues(TagEntity::TAG_ID_USER) &&
            $this->console->confirm('Would you like to remove old users from account_tag_values?')) {
            $this->console->out("Removing old users");
            $this->db->Execute("DELETE FROM account_tag_values WHERE tag_id = ?", array(
                TagEntity::TAG_ID_USER
            ));
        }
        $this->console->out('Populating users to the dictionary');

        $db = \Scalr::getDb();

        $stmt = '';

        //Retrieves data from scalr database
        $res = $db->Execute("
            SELECT
                u.`account_id`,
                ? `tag_id`,
                u.`id` `value_id`,
                COALESCE(u.fullname, u.email) `value_name`
            FROM `account_users` u
        ", [TagEntity::TAG_ID_USER]);

        while ($rec = $res->FetchRow()) {
            $stmt .= ',('
                  . $db->qstr($rec['account_id']) . ', '
                  . $db->qstr($rec['tag_id']) . ', '
                  . $db->qstr($rec['value_id']) . ', '
                  . $db->qstr($rec['value_name']) . ')'
            ;
        }

        if ($stmt != '') {
            //Inserts data to analytics database
            $this->db->Execute("
                INSERT IGNORE `account_tag_values` (`account_id`, `tag_id`, `value_id`, `value_name`)
                VALUES " . substr($stmt, 1) . "
            ");
        }
    }

    protected function isApplied7($stage)
    {
        return $this->isAppliedCommon(TagEntity::TAG_ID_COST_CENTRE);
    }

    protected function validateBefore7($stage)
    {
        return $this->hasTableService('ccs') && $this->validateCommon(TagEntity::TAG_ID_COST_CENTRE, 'Cost Centre');
    }

    protected function run7($stage)
    {
        if ($this->getCountOfAccountTagValues(TagEntity::TAG_ID_COST_CENTRE) &&
            $this->console->confirm('Would you like to remove old cost centres from account_tag_values?')) {
            $this->console->out("Removing old cost centres");
            $this->db->Execute("DELETE FROM account_tag_values WHERE tag_id = ?", array(
                TagEntity::TAG_ID_COST_CENTRE
            ));
        }
        $this->console->out('Populating cost centres to the dictionary');

        foreach (CostCentreEntity::all() as $ccEntity) {
            /* @var $ccEntity CostCentreEntity */
            $this->db->Execute("
                INSERT IGNORE `account_tag_values` (`account_id`, `tag_id`, `value_id`, `value_name`)
                VALUES (?, ?, ?, ?)
            ", [
                ($ccEntity->accountId ?: 0),
                TagEntity::TAG_ID_COST_CENTRE,
                $ccEntity->ccId,
                $ccEntity->name,
            ]);
        }
    }

    protected function isApplied8($stage)
    {
        return $this->isAppliedCommon(TagEntity::TAG_ID_PROJECT);
    }

    protected function validateBefore8($stage)
    {
        return $this->hasTableService('projects') && $this->validateCommon(TagEntity::TAG_ID_PROJECT, 'Projects');
    }

    protected function run8($stage)
    {
        if ($this->getCountOfAccountTagValues(TagEntity::TAG_ID_PROJECT) &&
            $this->console->confirm('Would you like to remove old projects from account_tag_values?')) {
            $this->console->out("Removing old projects");
            $this->db->Execute("DELETE FROM account_tag_values WHERE tag_id = ?", array(
                TagEntity::TAG_ID_PROJECT
            ));
        }
        $this->console->out('Populating projects to the dictionary');

        foreach (ProjectEntity::all() as $projectEntity) {
            /* @var $projectEntity ProjectEntity */
            $this->db->Execute("
                INSERT IGNORE `account_tag_values` (`account_id`, `tag_id`, `value_id`, `value_name`)
                VALUES (?, ?, ?, ?)
            ", [
                ($projectEntity->accountId ?: 0),
                TagEntity::TAG_ID_PROJECT,
                $projectEntity->projectId,
                $projectEntity->name,
            ]);
        }
    }

    protected function isApplied9($stage)
    {
        return false;
    }

    protected function validateBefore9($stage)
    {
        return $this->hasTableService('projects');
    }

    protected function run9($stage)
    {
        $usage = $this->container->analytics->usage;

        if (method_exists($usage, 'initDefault')) {
            $this->console->out('Initializing default cost centre and project...');
            //Creates cost centres and projects and assigns existing environments and farms to
            //cost centres and projects accordingly
            $usage->initDefault();
        }
    }
}