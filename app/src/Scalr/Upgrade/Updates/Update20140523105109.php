<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\GoogleCE\GoogleCEPlatformModule;
use Scalr\Modules\Platforms\Rackspace\RackspacePlatformModule;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Model\Entity\Image;

class Update20140523105109 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '2688fb9b-7792-4d2a-814b-1453fecf9149';

    protected $depends = array('d96f839f-6f51-4b78-a3df-56fdb56b1241');

    protected $description = 'Fill new table images from bundle_tasks and role_images';

    protected $ignoreChanges = false;

    public function getNumberStages()
    {
        return 3;
    }

    public  function validateBefore1()
    {
        return true;
    }

    public  function isApplied1($stage)
    {
        return $this->hasTable('images') && !$this->hasTableColumn('images', 'account_id');
    }

    public  function run1($stage)
    {
        if ($this->hasTable('images')) {
            $this->db->Execute('DROP TABLE images'); // drop old table if existed
        }

        $this->db->Execute("CREATE TABLE `images` (
              `hash` binary(16) NOT NULL,
              `id` varchar(128) NOT NULL DEFAULT '',
              `env_id` int(11) NULL DEFAULT NULL,
              `bundle_task_id` int(11) NULL DEFAULT NULL,
              `platform` varchar(25) NOT NULL DEFAULT '',
              `cloud_location` varchar(255) NOT NULL DEFAULT '',
              `os_family` varchar(25) NULL DEFAULT NULL,
              `os_version` varchar(10) NULL DEFAULT NULL,
              `os_name` varchar(255) NULL DEFAULT NULL,
              `created_by_id` int(11) NULL DEFAULT NULL,
              `created_by_email` varchar(100) NULL DEFAULT NULL,
              `architecture` enum('i386','x86_64') NOT NULL DEFAULT 'x86_64',
              `is_deprecated` tinyint(1) NOT NULL DEFAULT '0',
              `source` enum('BundleTask','Manual') NOT NULL DEFAULT 'Manual',
              `type` varchar(20) NULL DEFAULT NULL,
              `status` varchar(20) NOT NULL,
              `status_error` varchar(255) NULL DEFAULT NULL,
              `agent_version` varchar(20) NULL DEFAULT NULL,
              PRIMARY KEY (`hash`),
              UNIQUE KEY `idx_id` (`env_id`, `id`, `platform`, `cloud_location`),
              CONSTRAINT `fk_images_client_environmnets_id` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ");

        $allRecords = 0;
        $excludedCL = 0;
        $excludedMissing = 0;

        // convert
        $tasks = [];
        foreach ($this->db->GetAll('SELECT id as bundle_task_id, client_id as account_id, env_id, platform, snapshot_id as id, cloud_location, os_family, os_name,
            os_version, created_by_id, created_by_email, bundle_type as type FROM bundle_tasks WHERE status = ?', [\SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS]) as $t) {
            if (! is_array($tasks[$t['env_id']]))
                $tasks[$t['env_id']] = [];

            $allRecords++;
            $tasks[$t['env_id']][] = $t;
        }

        foreach ($this->db->GetAll('SELECT r.client_id as account_id, r.env_id, ri.platform, ri.image_id as id, ri.cloud_location, ri.os_family, ri.os_name,
            ri.os_version, r.added_by_userid as created_by_id, r.added_by_email as created_by_email, ri.agent_version FROM role_images ri JOIN roles r ON r.id = ri.role_id') as $t) {
            if (! is_array($tasks[$t['env_id']]))
                $tasks[$t['env_id']] = [];

            $allRecords++;
            $tasks[$t['env_id']][] = $t;
        }

        foreach ($tasks as $id => $e) {
            if ($id == 0)
                continue;

            try {
                $env = (new \Scalr_Environment())->loadById($id);
            } catch (\Exception $e) {
                $this->console->warning('Invalid environment %d: %s', $id, $e->getMessage());
                continue;
            }

            foreach ($e as $t) {
                // check if snapshot exists
                $add = false;
                if ($this->db->GetOne('SELECT id FROM images WHERE id = ? AND env_id = ? AND platform = ? AND cloud_location = ? LIMIT 1', [$t['id'], $t['env_id'], $t['platform'], $t['cloud_location']])) {
                    continue;
                }

                if ($t['platform'] != \SERVER_PLATFORMS::GCE && !$t['cloud_location']) {
                    $excludedCL++;
                    continue;
                }

                try {
                    switch ($t['platform']) {
                        case \SERVER_PLATFORMS::EC2:
                            $snap = $env->aws($t['cloud_location'])->ec2->image->describe($t['id']);
                            if (count($snap)) {
                                $add = true;
                                $t['architecture'] = $snap->toArray()[0]['architecture'];
                            }
                            break;

                        case \SERVER_PLATFORMS::RACKSPACE:
                            $platform = PlatformFactory::NewPlatform(\SERVER_PLATFORMS::RACKSPACE);
                            /* @var $platform RackspacePlatformModule */
                            $client = \Scalr_Service_Cloud_Rackspace::newRackspaceCS(
                                $env->getPlatformConfigValue(RackspacePlatformModule::USERNAME, true, $t['cloud_location']),
                                $env->getPlatformConfigValue(RackspacePlatformModule::API_KEY, true, $t['cloud_location']),
                                $t['cloud_location']
                            );

                            $snap = $client->getImageDetails($t['id']);
                            if ($snap) {
                                $add = true;
                            } else {
                                $excludedMissing++;
                            }
                            break;

                        case \SERVER_PLATFORMS::GCE:
                            $platform = PlatformFactory::NewPlatform(\SERVER_PLATFORMS::GCE);
                            /* @var $platform GoogleCEPlatformModule */
                            $client = $platform->getClient($env);
                            /* @var $client \Google_Service_Compute */
                            $projectId = $env->getPlatformConfigValue(GoogleCEPlatformModule::PROJECT_ID);
                            $snap = $client->images->get($projectId, str_replace($projectId . '/images/', '', $t['id']));

                            if ($snap) {
                                $add = true;
                                $t['architecture'] = 'x86_64';
                            } else {
                                $excludedMissing++;
                            }
                            break;

                        case \SERVER_PLATFORMS::EUCALYPTUS:
                            $snap = $env->eucalyptus($t['cloud_location'])->ec2->image->describe($t['id']);
                            if (count($snap)) {
                                $add = true;
                                $t['architecture'] = $snap->toArray()[0]['architecture'];
                            }
                            break;

                        default:
                            if (PlatformFactory::isOpenstack($t['platform'])) {
                                $snap = $env->openstack($t['platform'], $t['cloud_location'])->servers->getImage($t['id']);
                                if ($snap) {
                                    $add = true;
                                    $t['architecture'] = $snap->metadata->arch == 'x84-64' ? 'x84_64' : 'i386';
                                } else {
                                    $excludedMissing++;
                                }

                            } else if (PlatformFactory::isCloudstack($t['platform'])) {
                                $snap = $env->cloudstack($t['platform'])->template->describe(['templatefilter' => 'executable', 'id' => $t['id'], 'zoneid' => $t['cloud_location']]);
                                if ($snap) {
                                    if (isset($snap[0])) {
                                        $add = true;
                                    }
                                } else {
                                    $excludedMissing++;
                                }

                            } else {
                                $this->console->warning('Unknown platform: %s', $t['platform']);
                            }
                    }

                    if ($add) {
                        $image = new Image();
                        $image->id = $t['id'];
                        $image->envId = $t['env_id'];
                        $image->bundleTaskId = $t['bundle_task_id'];
                        $image->platform = $t['platform'];
                        $image->cloudLocation = $t['cloud_location'];
                        $image->createdById = $t['created_by_id'];
                        $image->createdByEmail = $t['created_by_email'];
                        $image->architecture = $t['architecture'] ? $t['architecture'] : 'x86_64';
                        $image->isDeprecated = 0;
                        $image->source = $t['bundle_task_id'] ? 'BundleTask' : 'Manual';
                        $image->type = $t['type'];
                        $image->status = Image::STATUS_ACTIVE;
                        $image->agentVersion = $t['agent_version'];

                        $image->save();
                    } else {
                        $excludedMissing++;
                    }
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'The resource could not be found') !== FALSE) {
                        $excludedMissing++;
                    } else if (strpos($e->getMessage(), 'The requested URL / was not found on this server.') !== FALSE) {
                        $excludedMissing++;
                    } else if (strpos($e->getMessage(), 'Not Found') !== FALSE) {
                        $excludedMissing++;
                    } else if (strpos($e->getMessage(), 'was not found') !== FALSE) {
                        $excludedMissing++;
                    } else if (strpos($e->getMessage(), 'Bad username or password') !== FALSE) {
                        $excludedMissing++;
                    } else if (strpos($e->getMessage(), 'unable to verify user credentials and/or request signature') !== FALSE) {
                        $excludedMissing++;
                    } else if (strpos($e->getMessage(), 'OpenStack error. Image not found.') !== FALSE) {
                        $excludedMissing++;
                    } else if (strpos($e->getMessage(), 'Neither api key nor password was provided for the OpenStack config.') !== FALSE) {
                        $excludedMissing++;
                    } else {
                        $this->console->warning('SnapshotId: %s, envId: %d, error: %s', $t['id'], $t['env_id'], $e->getMessage());
                    }
                }
            }
        }

        $this->console->notice('Found %d records', $allRecords);
        $this->console->notice('Excluded %d images because of null cloud_location', $excludedCL);
        $this->console->notice('Excluded %d missed images', $excludedMissing);
    }

    public function validateBefore2()
    {
        return $this->hasTable('images');
    }

    public function isApplied2()
    {
        return $this->hasTableColumn('images', 'hash');
    }

    public function run2()
    {
        if ($this->hasTableIndex('images', 'id')) {
            $this->db->Execute('ALTER TABLE images DROP KEY id, ADD UNIQUE KEY `idx_id` (`env_id`,`id`,`platform`,`cloud_location`)');
        }

        $this->db->Execute('ALTER TABLE images ADD `hash` binary(16) NOT NULL FIRST');

        foreach ($this->db->GetAll('SELECT * FROM images') as $im) {
            if ($im['env_id']) {
                $this->db->Execute('UPDATE images SET hash = UNHEX(?) WHERE env_id = ? AND id = ? AND platform = ? AND cloud_location = ?', [
                    str_replace('-', '', Image::calculateHash($im['env_id'], $im['id'], $im['platform'], $im['cloud_location'])),
                    $im['env_id'],
                    $im['id'],
                    $im['platform'],
                    $im['cloud_location']
                ]);
            } else {
                $this->db->Execute('UPDATE images SET hash = UNHEX(?) WHERE ISNULL(env_id) AND id = ? AND platform = ? AND cloud_location = ?', [
                    str_replace('-', '', Image::calculateHash($im['env_id'], $im['id'], $im['platform'], $im['cloud_location'])),
                    $im['id'],
                    $im['platform'],
                    $im['cloud_location']
                ]);
            }
        }

        // remove possible duplicates
        foreach ($this->db->GetAll('SELECT `hash`, count(*) as cnt FROM images GROUP BY hash HAVING cnt > 1') as $im) {
            $this->db->Execute('DELETE FROM images WHERE hash = ? LIMIT ?', [$im['hash'], $im['cnt'] - 1]);
        }

        $this->db->Execute('ALTER TABLE images ADD PRIMARY KEY(`hash`)');
    }

    public function isApplied3()
    {
        return $this->hasTableColumn('images', 'status_error');
    }

    public function run3()
    {
        $this->db->Execute('ALTER TABLE images ADD COLUMN `status_error` varchar(255) NULL DEFAULT NULL AFTER `status`');
    }
}
