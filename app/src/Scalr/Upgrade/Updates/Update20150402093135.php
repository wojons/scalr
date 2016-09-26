<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150402093135 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'cca60488-d8a5-4083-b331-4eecd6907744';

    protected $depends = [];

    protected $description = 'Modify role_images unique key';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    private $origin = 'role_images';

    private $sql = [];

    private static $oldIndex = [
        1 => 'role_id',
        2 => 'platform',
        3 => 'cloud_location',
        4 => 'image_id'
    ];

    private static $newIndex = [
        1 => 'platform',
        2 => 'cloud_location',
        3 => 'image_id',
        4 => 'role_id'
    ];

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 3;
    }

    protected function isApplied1($stage)
    {
        $indexes = $this->hasTableCompatibleIndex($this->origin, static::$oldIndex, true);

        return $this->hasTableCompatibleIndex($this->origin, static::$newIndex, true) ||
               (is_array($indexes) ? !in_array('key_idx', $indexes) : true);
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable($this->origin) && !$this->hasTableIndex($this->origin, 'uniq_image');
    }

    protected function run1($stage)
    {
        $this->console->out('Drop old `role_images` unique key');

        $this->sql[] = 'DROP INDEX `key_idx`';

        $this->console->out('Add unique key to `role_images`');

        $this->sql[] = 'ADD UNIQUE INDEX `uniq_image` (`platform` ASC, `cloud_location` ASC, `image_id` ASC, `role_id` ASC)';
    }

    protected function isApplied2($stage)
    {
        return $this->hasTableIndex($this->origin, 'idx_image_id');
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable($this->origin);
    }

    protected function run2($stage)
    {
        $this->console->out('Add index by `images_id` to `role_images`');

        $this->sql[] = 'ADD INDEX `idx_image_id` (`image_id` ASC)';
    }

    protected function isApplied3($stage)
    {
        return empty($this->sql);
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTable($this->origin);
    }

    protected function run3($stage)
    {
        $this->console->out('Apply changes on `role_images`');

        $this->applyChanges($this->origin, $this->sql);
    }
}