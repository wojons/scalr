<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Exception;
use Scalr\Acl\Acl;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Exception\ApiInsufficientPermissionsException;
use Scalr\Api\Rest\Exception\ApiNotImplementedErrorException;
use Scalr\Api\Service\User\V1beta0\Adapter\ServerAdapter;
use Scalr\Model\Entity\Farm;
use Scalr\Model\Entity\FarmTeam;
use Scalr\Model\Entity\Server;
use Scalr\Modules\PlatformFactory;
use Scalr\Service\Exception\InstanceNotFound;
use SERVER_PLATFORMS;
use EC2_SERVER_PROPERTIES;
use ROLE_BEHAVIORS;

/**
 * User/Servers API Controller
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 */
class Servers extends ApiController
{
    /**
     * Gets Default search criteria for the Environment scope
     *
     * @return array Returns array of the default search criteria for the Environment scope
     */
    private function getDefaultCriteria()
    {
        $server = new Server();

        $defaultCondition = sprintf("{$server->columnAccountId()} = %d AND {$server->columnEnvId()} = %d ", $this->getUser()->accountId, $this->getEnvironment()->id);
        $and = " AND {$server->columnFarmId()} IS NOT NULL ";

        if (!$this->hasPermissions(Acl::RESOURCE_FARMS)) {
            $where = [];
            $farm = new Farm();
            $farmTeam = new FarmTeam();

            $join[] = " LEFT JOIN {$farm->table('f')} ON {$farm->columnId('f')} = {$server->columnFarmId()}";

            if ($this->hasPermissions(Acl::RESOURCE_OWN_FARMS)) {
                $where[] = "{$farm->columnOwnerId('f')} = " . $farm->qstr('ownerId', $this->getUser()->id);
            }

            if ($this->hasPermissions(Acl::RESOURCE_TEAM_FARMS)) {
                $join[] = "
                    LEFT JOIN {$farmTeam->table('ft')} ON {$farmTeam->columnFarmId('ft')} = {$farm->columnId('f')}
                    LEFT JOIN `account_team_users` `atu` ON `atu`.`team_id` = {$farmTeam->columnTeamId('ft')}
                    LEFT JOIN `account_team_envs` `ate` ON `ate`.`team_id` = {$farmTeam->columnTeamId('ft')} AND `ate`.`env_id` = {$farm->columnEnvId('f')}
                ";
                $where[] = "`atu`.`user_id` = " . $farmTeam->db()->qstr($this->getUser()->id) . " AND `ate`.`team_id` IS NOT NULL";
            }

            if (!empty($where)) {
                $criteria[Farm::STMT_WHERE] = '(' . $defaultCondition . $and . ' AND ' . join(' OR ', $where) . ')';
            }

            $criteria[Farm::STMT_FROM] = $server->table() . implode(' ', $join);
        }

        // add Temporary and Importing Servers to response
        if ($this->hasPermissions(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE)) {
            $extraCondition = sprintf("({$defaultCondition} AND {$server->columnFarmId()} IS NULL AND {$server->columnStatus()} IN ('%s', '%s'))", Server::STATUS_IMPORTING, Server::STATUS_TEMPORARY);

            if (empty($criteria[Farm::STMT_WHERE]) && $this->hasPermissions(Acl::RESOURCE_FARMS)) {
                $criteria[Farm::STMT_WHERE] = "(({$defaultCondition}{$and}) OR {$extraCondition})";
            } else if (empty($criteria[Farm::STMT_WHERE])) {
                $criteria[Farm::STMT_WHERE] = "{$extraCondition}";
            } else {
                $criteria[Farm::STMT_WHERE] = "({$criteria[Farm::STMT_WHERE]} OR {$extraCondition})";
            }
        }

        if (empty($criteria[Farm::STMT_WHERE])) {
            $criteria[Farm::STMT_WHERE] = $defaultCondition;
        }

        return $criteria;
    }

    /**
     * Retrieves the list of the roles that are available on the Environment scope
     */
    public function describeAction()
    {
        if (!$this->hasPermissions(Acl::RESOURCE_FARMS) &&
            !$this->hasPermissions(Acl::RESOURCE_TEAM_FARMS) &&
            !$this->hasPermissions(Acl::RESOURCE_OWN_FARMS) &&
            !$this->hasPermissions(Acl::RESOURCE_IMAGES_ENVIRONMENT, ACL::PERM_IMAGES_ENVIRONMENT_MANAGE)) {
            throw new ApiInsufficientPermissionsException();
        }

        return $this->adapter('server')->getDescribeResult($this->getDefaultCriteria(), [Server::class, 'findWithProperties']);
    }

    /**
     * Gets specified Server
     *
     * @param   string      $serverId           UUID of the server
     *
     * @return  Server    Returns the Server Entity on success
     * @throws  ApiErrorException
     */
    public function getServer($serverId)
    {
        $criteria = [['serverId' => $serverId]];
        $server = Server::findOne(array_merge($this->getDefaultCriteria(), $criteria));
        /* @var $server Server */
        if (!$server) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Server either does not exist or is not owned by your environment.");
        }

        if (!$this->hasPermissions($server)) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }

        return $server;
    }

    /**
     * Fetches detailed info about one server
     *
     * @param  string $serverId  UUID of the server
     *
     * @return  ResultEnvelope
     */
    public function fetchAction($serverId)
    {
        return $this->result($this->adapter('server')->toData($this->getServer($serverId)));
    }

    /**
     * Stops instance
     *
     * @param string    $serverId   UUID of the server
     * @return ResultEnvelope
     * @throws ApiErrorException
     */
    public function suspendAction($serverId)
    {
        $server = $this->getServer($serverId);

        $this->checkPermissions($server, Acl::PERM_FARMS_SERVERS);

        if ($server->platform == SERVER_PLATFORMS::EC2) {
            $image = $server->getImage();
            if (!empty($image) && $image->isEc2InstanceStoreImage()) {
                throw new ApiErrorException(409, ErrorMessage::ERR_CONFIGURATION_MISMATCH, "The instance does not have an 'ebs' root device type and cannot be suspended");
            }
        }

        $farmRole = $server->getFarmRole();
        if (!empty($farmRole)) {
            $role = $farmRole->getRole();
            if ($role->hasDbBehavior()) {
                throw new ApiErrorException(409, ErrorMessage::ERR_CONFIGURATION_MISMATCH, "Instance with database built in automation can not be stopped");
            }

            if ($role->hasBehavior(ROLE_BEHAVIORS::RABBITMQ)) {
                throw new ApiErrorException(409, ErrorMessage::ERR_CONFIGURATION_MISMATCH, "Instance with rabbitmq built in automation can not be stopped");
            }
        }

        if (!$server->suspend($this->getUser())) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNACCEPTABLE_STATE, "Only Running Servers can be suspended.");
        }

        $this->response->setStatus(200);

        return $this->result($this->adapter('server')->toData($server));
    }

    /**
     * Terminates instance
     *
     * @param string    $serverId   UUID of the server
     * @return ResultEnvelope
     * @throws ApiErrorException
     */
    public function terminateAction($serverId)
    {
        $server = $this->getServer($serverId);

        $this->checkPermissions($server, Acl::PERM_FARMS_SERVERS);

        if (in_array($server->status, [Server::STATUS_IMPORTING, Server::STATUS_TEMPORARY])) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNACCEPTABLE_STATE, sprintf("The Server can't be terminated in %s state.", $server->status));
        }

        if ($server->platform == SERVER_PLATFORMS::EC2 && !empty($server->properties[EC2_SERVER_PROPERTIES::IS_LOCKED])) {
            throw new ApiErrorException(409, ErrorMessage::ERR_LOCKED, "Server has disableAPITermination flag and can not be terminated.");
        }

        $object = $this->request->getJsonBody();
        $force = isset($object->force) ? ServerAdapter::convertInputValue('boolean', $object->force) : false;

        if ((PlatformFactory::isOpenstack($server->platform) || PlatformFactory::isCloudstack($server->platform)) && $force) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, sprintf("Force termination is not available for platform %s.", $server->platform));
        }

        if (!$server->terminate([Server::TERMINATE_REASON_MANUALLY_API, $this->getUser()->fullName], $force, $this->getUser())) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNACCEPTABLE_STATE, sprintf("Server with id %s has already been terminated.", $serverId));
        }

        $this->response->setStatus(200);

        return $this->result($this->adapter('server')->toData($server));
    }

    /**
     * Resumes instance
     *
     * @param string    $serverId   UUID of the server
     * @return ResultEnvelope
     * @throws ApiErrorException
     */
    public function resumeAction($serverId)
    {
        $server = $this->getServer($serverId);

        $this->checkPermissions($server, Acl::PERM_FARMS_SERVERS);

        if ($server->status != Server::STATUS_SUSPENDED) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNACCEPTABLE_STATE, "Only Suspended Servers can be resumed.");
        }

        try {
            $server->resume();
        } catch (InstanceNotFound $e) {
            throw new ApiErrorException(409, ErrorMessage::ERR_OBJECT_NOT_FOUND_ON_CLOUD, "The Server that you are trying to use does not exist on the cloud.");
        } catch (Exception $e) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNACCEPTABLE_OBJECT_CONFIGURATION, $e->getMessage());
        }

        $this->response->setStatus(200);

        return $this->result($this->adapter('server')->toData($server));
    }

    /**
     * Reboots instance
     *
     * @param string    $serverId   UUID of the server
     * @return ResultEnvelope
     * @throws ApiErrorException
     * @throws ApiNotImplementedErrorException
     */
    public function rebootAction($serverId)
    {
        $server = $this->getServer($serverId);

        $this->checkPermissions($server, Acl::PERM_FARMS_SERVERS);

        if (in_array($server->status, [
            Server::STATUS_TERMINATED,
            Server::STATUS_IMPORTING,
            Server::STATUS_PENDING_LAUNCH,
            Server::STATUS_PENDING_TERMINATE,
            Server::STATUS_TEMPORARY
        ])) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNACCEPTABLE_STATE, sprintf("The Server can't be rebooted in %s state.", $server->status));
        }

        if ($server->platform == SERVER_PLATFORMS::AZURE) {
            throw new ApiNotImplementedErrorException('Reboot action has not been implemented for Azure cloud platform yet.');
        }

        $object = $this->request->getJsonBody();
        $hard = isset($object->hard) ? ServerAdapter::convertInputValue('boolean', $object->hard) : false;

        try {
            $server->reboot($hard);
        } catch (InstanceNotFound $e) {
            throw new ApiErrorException(409, ErrorMessage::ERR_OBJECT_NOT_FOUND_ON_CLOUD, "The Server that you are trying to use does not exist on the cloud.");
        } catch (Exception $e) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNACCEPTABLE_OBJECT_CONFIGURATION, $e->getMessage());
        }

        $this->response->setStatus(200);

        return $this->result($this->adapter('server')->toData($server));
    }

}