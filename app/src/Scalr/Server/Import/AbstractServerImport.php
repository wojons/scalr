<?php
namespace Scalr\Server\Import;

use Scalr\Exception\ValidationErrorException;
use Scalr\Exception\ServerImportException;
use Scalr\Model\Entity;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\OrphanedServer;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Service\Aws;
use SZR_KEY_TYPE;
use DBServer;
use DateTime;
use Exception;

/**
 * Server import Abstract class
 *
 * @author  Igor Vodiasov <invar@scalr.com>
 * @since   5.11.5 (22.01.2016)
 */
abstract class AbstractServerImport implements ServerImportInterface
{
    /**
     * @var Entity\Account\User
     */
    protected $user;

    /**
     * @var Entity\FarmRole
     */
    protected $farmRole;

    /**
     * @var Entity\Server
     */
    protected $server;

    /**
     * @var OrphanedServer
     */
    protected $orphaned;

    /**
     * @var array
     */
    protected $tags;

    /**
     * @var \ADODB_mysqli
     */
    protected $db;

    /**
     * Constructor
     *
     * @param   Entity\FarmRole     $farmRole The Farm Role entity
     * @param   Entity\Account\User $user     The User entity
     */
    public function __construct($farmRole, $user)
    {
        $this->farmRole = $farmRole;
        $this->user = $user;
        $this->db = \Scalr::getDb();
    }

    /**
     * Check if instances are valid
     *
     * @throws  ValidationErrorException
     */
    protected function validate()
    {
        $farm = $this->farmRole->getFarm();

        if ($farm->status != Entity\Farm::STATUS_RUNNING) {
            throw new ValidationErrorException('Farm should be in the Running state to import the Instance.');
        }

        $role = $this->farmRole->getRole();
        if ($role->isScalarized) {
            throw new ValidationErrorException('Only non-scalarized Roles are supported.');
        }

        if ($this->farmRole->getImage()->id != $this->orphaned->imageId) {
            throw new ValidationErrorException('ID of the FarmRole Image must match ID of the Image of the Instance.');
        }

        if ($this->farmRole->settings[Entity\FarmRoleSetting::SCALING_ENABLED] == 1) {
            throw new ValidationErrorException('Scaling should be set to manual before importing instance');
        }
    }

    /**
     * Fill cloud-specific parameters
     */
    abstract protected function importServer();

    /**
     * Apply meta-tag to instance on cloud
     */
    abstract protected function applyTags();

    /**
     * {@inheritdoc}
     * @see ServerImportInterface::import()
     */
    public function import($instanceId, $tags = [])
    {
        $instances = PlatformFactory::NewPlatform($this->farmRole->platform)->getOrphanedServers(
            $this->farmRole->getFarm()->getEnvironment(),
            $this->farmRole->cloudLocation,
            [$instanceId]
        );

        if (count($instances) != 1) {
            throw new ValidationErrorException("Instance was not found");
        }

        $this->orphaned = $instances[0];
        $this->tags = $tags;
        $this->validate();
        $farm = $this->farmRole->getFarm();
        $server = $this->server = new Entity\Server();

        try {
            $server->serverId = \Scalr::GenerateUID(false); // DBServer::Create, startWithLetter
            $server->platform = $this->farmRole->platform;
            $server->cloudLocation = $this->farmRole->cloudLocation;
            $server->accountId = $farm->accountId;
            $server->envId = $farm->envId;
            $server->farmId = $farm->id;
            $server->farmRoleId = $this->farmRole->id;
            $server->imageId = $this->orphaned->imageId;
            $server->status = Entity\Server::STATUS_RUNNING;
            $server->type = $this->orphaned->instanceType;
            $server->remoteIp = $this->orphaned->publicIp;
            $server->localIp = $this->orphaned->privateIp;
            $server->added = new DateTime();
            $server->initialized = new DateTime(); // initialized is used in billing, so we set current time as start point
            $server->scalarized = 0;

            $server->setFreeFarmIndex();
            $server->setFreeFarmRoleIndex();

            $server->properties[Entity\Server::SZR_KEY] = \Scalr::GenerateRandomKey(40);
            $server->properties[Entity\Server::SZR_KEY_TYPE] = SZR_KEY_TYPE::ONE_TIME;
            $server->properties[Entity\Server::SZR_VESION] = '';
            $server->properties[Entity\Server::LAUNCHED_BY_ID] = $this->user->id;
            $server->properties[Entity\Server::LAUNCHED_BY_EMAIL] = $this->user->email;
            $server->properties[Entity\Server::LAUNCH_REASON_ID] = DBServer::LAUNCH_REASON_IMPORT;
            $server->properties[Entity\Server::LAUNCH_REASON] = DBServer::getLaunchReason(DBServer::LAUNCH_REASON_IMPORT);
            $server->properties[Entity\Server::FARM_ROLE_ID] = $this->farmRole->id;
            $server->properties[Entity\Server::ROLE_ID] = $this->farmRole->roleId;
            $server->properties[Entity\Server::FARM_CREATED_BY_ID] = $farm->ownerId ?: $farm->settings[Entity\FarmSetting::CREATED_BY_ID];
            $server->properties[Entity\Server::FARM_CREATED_BY_EMAIL] = $farm->ownerId ? Entity\Account\User::findPk($farm->ownerId)->email : $farm->settings[Entity\FarmSetting::CREATED_BY_EMAIL];

            // projectId, ccId
            $projectId = $farm->settings[Entity\FarmSetting::PROJECT_ID];
            $ccId = null;
            if (!empty($projectId)) {
                try {
                    $projectEntity = ProjectEntity::findPk($projectId);

                    if ($projectEntity instanceof ProjectEntity) {
                        /* @var $projectEntity ProjectEntity */
                        $ccId = $projectEntity->ccId;
                    } else {
                        $projectId = null;
                    }
                } catch (Exception $e) {
                    $projectId = null;
                }
            }
            $server->properties[Entity\Server::FARM_PROJECT_ID] = $projectId;

            if (empty($ccId)) {
                $ccId = Entity\Account\Environment::findPk($farm->envId)->getProperty(Entity\Account\EnvironmentProperty::SETTING_CC_ID);
            }

            $server->properties[Entity\Server::ENV_CC_ID] = $ccId;

            if (!empty($server->getImage())) {
                $server->getImage()->update(['dtLastUsed' => new DateTime()]);
            }

            if (!empty($this->farmRole->getRole())) {
                $this->farmRole->getRole()->update(['lastUsed' => new DateTime()]);
            }

            $this->importServer();

            $server->save();
            $server->setTimeLog('ts_created');
            $server->setTimeLog('ts_launched', time());

            $history = $server->getHistory();

            $history->markAsLaunched(
                $server->properties[Entity\Server::LAUNCH_REASON],
                $server->properties[Entity\Server::LAUNCH_REASON_ID]
            );

            $history->update(['cloudServerId' => $this->orphaned->cloudServerId, 'scuCollecting' => 1]);

            $this->applyTags();

            return $server;
        } catch (Exception $e) {
            if (!empty($server->serverId)) {
                // cleanup
                $server->deleteBy([['serverId' => $server->serverId]]);
                Entity\ServerProperty::deleteBy([['serverId' => $server->serverId]]);
                Entity\Server\History::deletePk($server->serverId);
                $this->db->Execute("DELETE FROM `servers_launch_timelog` WHERE server_id = ?", [$server->serverId]);
            }

            throw new ServerImportException(sprintf("Server create was failed with error: %s", $e->getMessage()), $e->getCode(), $e);
        }
    }
}
