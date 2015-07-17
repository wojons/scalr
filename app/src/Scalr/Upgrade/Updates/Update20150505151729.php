<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150505151729 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '5ff9c2e8-2e38-432d-b1df-947ee1a4640c';

    protected $depends = [];

    protected $description = "Add indexes to images, roles, os, role_categories";

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 4;
    }

    protected function isApplied1($stage)
    {
        return false;
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('images');
    }

    protected function run1($stage)
    {
        $table = 'images';

        $sql = [];

        if ($this->hasTableColumn($table, 'name') && !$this->hasTableIndex($table, 'idx_name')) {
            $this->console->out('Add index by `name` to `images`');
            $sql[] = 'ADD INDEX `idx_name` (name(16))';
        }

        if ($this->hasTableColumn($table, 'status') && !$this->hasTableIndex($table, 'idx_status')) {
            $this->console->out('Add index by `status` to `images`');
            $sql[] = 'ADD INDEX `idx_status` (status)';
        }

        if ($this->hasTableColumn($table, 'os_id') && !$this->hasTableIndex($table, 'idx_os_id')) {
            $this->console->out('Add index by `os_id` to `images`');
            $sql[] = 'ADD INDEX `idx_os_id` (os_id)';
        }

        if ($this->hasTableColumn($table, 'id') && !$this->hasTableIndex($table, 'idx_image_id')) {
            $this->console->out('Add index by `id` to `images`');
            $sql[] = 'ADD INDEX `idx_image_id` (id)';
        }

        if ($this->hasTableColumn($table, 'platform') && $this->hasTableColumn($table, 'cloud_location') && !$this->hasTableIndex($table, 'idx_cloud_location')) {
            $this->console->out('Add index by `platform`  and `cloud_location` to `images`');
            $sql[] = 'ADD INDEX `idx_cloud_location` (platform, cloud_location)';
        }

        if (!empty($sql))
            $this->applyChanges($table, $sql);
    }

    protected function isApplied2($stage)
    {
        return false;
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('roles');
    }

    protected function run2($stage)
    {
        $table = 'roles';

        $sql = [];

        if ($this->hasTableColumn($table, 'origin')) {
            if ($this->hasTableIndex($table, 'NewIndex1')) {
                $this->console->out('Drop `NewIndex1` from `roles`');
                $sql[] = 'DROP INDEX `NewIndex1`';
            }

            if (!$this->hasTableIndex($table, 'idx_origin')) {
                $this->console->out('Add index by `origin` to `roles`');
                $sql[] = 'ADD INDEX `idx_origin` (origin)';
            }
        }

        if ($this->hasTableColumn($table, 'client_id')) {
            if ($this->hasTableIndex($table, 'NewIndex2')) {
                $this->console->out('Drop `NewIndex2` from `roles`');
                $sql[] = 'DROP INDEX `NewIndex2`';
            }

            if (!$this->hasTableIndex($table, 'idx_client_id')) {
                $this->console->out('Add index by `client_id` to `roles`');
                $sql[] = 'ADD INDEX `idx_client_id` (client_id)';
            }
        }

        if ($this->hasTableColumn($table, 'env_id')) {
            if ($this->hasTableIndex($table, 'NewIndex3')) {
                $this->console->out('Drop `NewIndex3` from `roles`');
                $sql[] = 'DROP INDEX `NewIndex3`';
            }

            if (!$this->hasTableIndex($table, 'idx_env_id')) {
                $this->console->out('Add index by `env_id` to `roles`');
                $sql[] = 'ADD INDEX `idx_env_id` (env_id)';
            }
        }

        if ($this->hasTableColumn($table, 'name') && !$this->hasTableIndex($table, 'idx_name')) {
            $this->console->out('Add index by `name` to `roles`');
            $sql[] = 'ADD INDEX `idx_name` (name(16))';
        }

        if ($this->hasTableColumn($table, 'cat_id') && !$this->hasTableIndex($table, 'idx_cat_id')) {
            $this->console->out('Add index by `cat_id` to `roles`');
            $sql[] = 'ADD INDEX `idx_cat_id` (cat_id)';
        }

        if ($this->hasTableColumn($table, 'os_id') && !$this->hasTableIndex($table, 'idx_os_id')) {
            $this->console->out('Add index by `os_id` to `roles`');
            $sql[] = 'ADD INDEX `idx_os_id` (os_id)';
        }

        if (!empty($sql))
            $this->applyChanges($table, $sql);
    }

    protected function isApplied3($stage)
    {
        return false;
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTable('role_categories');
    }

    protected function run3($stage)
    {
        $table = 'role_categories';

        $sql = [];

        if ($this->hasTableColumn($table, 'name') && !$this->hasTableIndex($table, 'idx_name')) {
            $this->console->out('Add index by `name` to `role_categories`');
            $sql[] = 'ADD INDEX `idx_name` (name(16))';
        }

        if ($this->hasTableColumn($table, 'env_id') && !$this->hasTableIndex($table, 'idx_env_id')) {
            $this->console->out('Add index by `env_id` to `role_categories`');
            $sql[] = 'ADD INDEX `idx_env_id` (env_id)';
        }

        if (!empty($sql))
            $this->applyChanges($table, $sql);
    }

    protected function isApplied4($stage)
    {
        return false;
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTable('os');
    }

    protected function run4($stage)
    {
        $table = 'os';

        $sql = [];

        if ($this->hasTableColumn($table, 'name') && !$this->hasTableIndex($table, 'idx_name')) {
            $this->console->out('Add index by `name` to `os`');
            $sql[] = 'ADD INDEX `idx_name` (name(16))';
        }

        if ($this->hasTableColumn($table, 'family') && !$this->hasTableIndex($table, 'idx_family')) {
            $this->console->out('Add index by `family` to `os`');
            $sql[] = 'ADD INDEX `idx_family` (family)';
        }

        if ($this->hasTableColumn($table, 'generation') && !$this->hasTableIndex($table, 'idx_generation')) {
            $this->console->out('Add index by `generation` to `os`');
            $sql[] = 'ADD INDEX `idx_generation` (generation)';
        }

        if (!empty($sql))
            $this->applyChanges($table, $sql);
    }

}