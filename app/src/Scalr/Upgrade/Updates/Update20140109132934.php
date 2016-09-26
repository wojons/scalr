<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Exception\UpgradeException;

/**
 * Checking if analytics database does exist
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @version  5.0 (09.01.2014)
 */
class Update20140109132934 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '22dd3ef7-9431-4d27-bf23-07d7deb00776';

    protected $depends = ['b6accbac-5201-4e59-ad67-2b2e907453d6'];

    protected $description = 'Checking if analytics database does exist';

    protected $ignoreChanges = true;

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::isRefused()
     */
    public function isRefused()
    {
        return !$this->container->analytics->enabled ? "Cost analytics is turned off" : false;
    }

    protected function isApplied1($stage)
    {
        if (!$this->container->analytics->enabled) {
            return false;
        }
        try {
            $db = $this->container->cadb;
            $ret = is_object($db) && ($db->GetOne("SHOW DATABASES LIKE ?", array(
                $this->container->config('scalr.analytics.connections.analytics.name')
            )) ? true : false);
        } catch (\Exception $e) {
            $ret = false;
        }
        return $ret;
    }

    protected function validateBefore1($stage)
    {
        $ret = $this->container->analytics->enabled;
        return $ret;
    }

    protected function run1($stage)
    {
        $fmt = $this->console->timeformat;
        $database = $this->container->config('scalr.analytics.connections.analytics.name');
        $mysqlUser = $this->container->config('scalr.analytics.connections.analytics.user');
        $this->console->error('To create a new database please execute following queries:');
        $this->console->timeformat = '';
        $this->console->out("CREATE SCHEMA IF NOT EXISTS `%s` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;", $database);
        $this->console->out("GRANT ALL ON %s.* TO '%s'@'localhost';", $database, $mysqlUser);
        $this->console->timeformat = $fmt;
        $this->console->warning("After that you should run upgrade script again to proceed.");
        $this->console->warning("It is recommended to use separate database server for Cost Analytics.");

        throw new UpgradeException();
    }
}