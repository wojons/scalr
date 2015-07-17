<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Stats\CostAnalytics\Entity\TagEntity;

/**
 * Analytics 3 phase database updates
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @version  5.0 (04.08.2014)
 */
class Update20140825110000 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '922d2ccd-df09-40f9-8d4f-1366bcaf988b';

    protected $depends = [];

    protected $description = "analytics phase 3 intermediary update";

    protected $ignoreChanges = false;

    protected $dbservice = 'cadb';

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::isRefused()
     */
    public function isRefused()
    {
        return !$this->container->analytics->enabled ? "Cost analytics is turned off" : false;
    }

    private function hasTagRole()
    {
        $tagRole = TagEntity::findPk(TagEntity::TAG_ID_ROLE);

        return $tagRole && strtolower($tagRole->name) == 'role';
    }

    private function hasTagRoleBehavior()
    {
        $tagRoleBehavior = TagEntity::findPk(TagEntity::TAG_ID_ROLE_BEHAVIOR);

        return $tagRoleBehavior && strtolower($tagRoleBehavior->name) == 'role behavior';
    }

    /**
     * Checks whether FARM_OWNER tag exists
     *
     * @return  boolean Returns true if FARM_OWNER tag does exist
     */
    private function hasTagFarmOwner()
    {
        return !!TagEntity::findPk(TagEntity::TAG_ID_FARM_OWNER);
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
        return $this->hasTagRole() &&
               $this->hasTagRoleBehavior() &&
               $this->hasTagFarmOwner();
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        if (!$this->hasTagRole()) {
            $this->console->out("Adding Role tag...");

            $tagRole = TagEntity::findPk(TagEntity::TAG_ID_ROLE) ?: new TagEntity();

            $tagRole->tagId = TagEntity::TAG_ID_ROLE;
            $tagRole->name = 'Role';

            $tagRole->save();
        }

        if (!$this->hasTagRoleBehavior()) {
            $this->console->out("Adding Role behavior tag...");

            $tagRoleBehavior = TagEntity::findPk(TagEntity::TAG_ID_ROLE_BEHAVIOR) ?: new TagEntity();

            $tagRoleBehavior->tagId = TagEntity::TAG_ID_ROLE_BEHAVIOR;
            $tagRoleBehavior->name = 'Role behavior';

            $tagRoleBehavior->save();
        }

        if (!$this->hasTagFarmOwner()) {
            $this->console->out("Adding Farm owner tag...");

            $tagFarmOwner = TagEntity::findPk(TagEntity::TAG_ID_FARM_OWNER) ?: new TagEntity();

            $tagFarmOwner->tagId = TagEntity::TAG_ID_FARM_OWNER;
            $tagFarmOwner->name = 'Farm owner';

            $tagFarmOwner->save();
        }
    }
}
