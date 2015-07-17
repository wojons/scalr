<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Modules\PlatformFactory;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Modules\Platforms\GoogleCE\GoogleCEPlatformModule;

class Update20141029152128 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'aa0f9936-0c71-4379-bc51-c8653eadf1c4';

    protected $depends = [];

    protected $description = 'Refactor table servers';

    protected $ignoreChanges = false;

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
        return !$this->hasTableColumn('servers', 'role_id');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('servers');
    }

    protected function run1($stage)
    {
        $this->console->notice('Remove column role_id from servers');
        $this->db->Execute("ALTER TABLE servers DROP COLUMN role_id");
    }

    protected function isApplied2()
    {
        return !$this->hasTableColumn('roles', 'history');
    }

    protected function validateBefore2()
    {
        return $this->hasTable('roles');
    }

    protected function run2()
    {
        $this->console->notice('Remove column history from roles');
        $this->db->Execute("ALTER TABLE roles DROP COLUMN history");
    }

    protected function isApplied3()
    {
        return $this->hasTableColumn('servers', 'image_id');
    }

    protected function run3()
    {
        $this->console->notice('Add column image_id to servers');
        $this->db->Execute("ALTER TABLE servers ADD COLUMN image_id VARCHAR(255) DEFAULT NULL AFTER cloud_location_zone, ADD INDEX idx_image_id(image_id)");
    }

    protected function isApplied4()
    {
        return !$this->db->GetOne("SELECT EXISTS(SELECT 1 FROM servers WHERE image_id IS NULL)");
    }

    protected function validateBefore4()
    {
        return $this->hasTableColumn('servers', 'image_id');
    }

    protected function run4()
    {
        $this->console->notice('Fill image_id in servers table');

        // ec2
        $this->db->Execute("
            UPDATE servers s JOIN server_properties sp ON s.server_id = sp.server_id
            SET s.image_id = sp.value
            WHERE sp.name = ? AND s.platform = ?
        ", [\EC2_SERVER_PROPERTIES::AMIID, \SERVER_PLATFORMS::EC2]);

        // rackspace
        $this->db->Execute("
            UPDATE servers s JOIN server_properties sp ON s.server_id = sp.server_id
            SET s.image_id = sp.value
            WHERE sp.name = ? AND s.platform = ?
        ", [\RACKSPACE_SERVER_PROPERTIES::IMAGE_ID, \SERVER_PLATFORMS::RACKSPACE]);

        //cloudstack
        foreach (PlatformFactory::getCloudstackBasedPlatforms() as $platform) {
            foreach ($this->db->GetCol('SELECT server_id FROM servers WHERE platform = ?', [$platform]) as $serverId) {
                try {
                    $dbServer = \DBServer::LoadByID($serverId);
                    $env = (new \Scalr_Environment())->loadById($dbServer->envId);
                    if ($dbServer->GetProperty(\CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID)) {
                        $lst = $env->cloudstack($platform)->instance->describe(['id' => $dbServer->GetProperty(\CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID)]);
                        if ($lst && $lst->count() == 1) {
                            $instance = $lst->offsetGet(0);
                            $dbServer->imageId = $instance->templateid;
                            $this->console->notice('Set imageId: %s for serverId: %s', $dbServer->imageId, $serverId);
                            $dbServer->save();
                        } else {
                            $this->console->warning('Instance not found: %s for serverId: %s', $dbServer->GetProperty(\CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID), $serverId);
                        }
                    }
                } catch (\Exception $e) {
                    $this->console->warning($e->getMessage());
                }
            }
        }

        // openstack
        foreach (PlatformFactory::getOpenstackBasedPlatforms() as $platform) {
            $this->db->Execute("
                UPDATE servers s LEFT JOIN server_properties sp ON s.server_id = sp.server_id
                SET s.image_id = sp.value
                WHERE sp.name = ? AND s.platform = ?
            ", [\OPENSTACK_SERVER_PROPERTIES::IMAGE_ID, $platform]);
        }
    }
}
