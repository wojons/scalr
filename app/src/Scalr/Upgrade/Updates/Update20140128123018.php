<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Stats\CostAnalytics\Usage;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;

class Update20140128123018 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'c541de8d-f07d-4a77-9f3e-a5470dba592e';

    protected $depends = ['22dd3ef7-9431-4d27-bf23-07d7deb00777'];

    protected $description = "Checks if default cost center does exist";

    protected $ignoreChanges = true;

    protected $dbservice = 'cadb';

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
        return 1;
    }

    protected function isApplied1($stage)
    {
        return CostCentreEntity::findPk(Usage::DEFAULT_CC_ID) !== null &&
               ProjectEntity::findPk(Usage::DEFAULT_PROJECT_ID) !== null;
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $usage = $this->container->analytics->usage;
        $usage->createDefaultCostCenter();
    }
}