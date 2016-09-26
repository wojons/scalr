<?php

namespace Scalr\Server\Import\Platforms;

use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Server\Import\AbstractServerImport;
use Scalr\Model\Entity;
use Scalr\Exception\ValidationErrorException;
use Scalr\Exception\ServerImportException;
use Scalr\Service\Aws;
use Scalr\Modules\PlatformFactory;
use Scalr\Service\Aws\Ec2\DataType;
use EC2_SERVER_PROPERTIES;
use Scalr_Governance;
use DBServer;
use SERVER_PLATFORMS;
use Exception;

/**
 * Server import
 *
 * @author  Igor Vodiasov <invar@scalr.com>
 * @since   5.11.5 (22.01.2016)
 */
class Ec2ServerImport extends AbstractServerImport
{
    /**
     * @var DataType\InstanceData
     */
    protected $instance;

    /**
     * {@inheritdoc}
     * @see AbstractServerImport::validate
     */
    protected function validate()
    {
        parent::validate();

        if ($this->orphaned->status != DataType\InstanceStateData::NAME_RUNNING) {
            throw new ValidationErrorException("Instance must be in the Running state.");
        }

        if (isset($this->tags[Scalr_Governance::SCALR_META_TAG_NAME])) {
            throw new ValidationErrorException(sprintf("It is not permitted to set %s tag. Scalr itself sets this tag.", Scalr_Governance::SCALR_META_TAG_NAME));
        }

        $tags = [Scalr_Governance::SCALR_META_TAG_NAME => Scalr_Governance::SCALR_META_TAG_NAME];
        $tags = array_merge($tags, $this->tags);

        foreach ($this->orphaned->tags as $t) {
            $tags[$t['key']] = $t['value'];
        }

        if (count($tags) > Ec2PlatformModule::MAX_TAGS_COUNT) {
            throw new ValidationErrorException(sprintf("Not enough capacity to add tags to the Instance. %d tags are allowed.", Ec2PlatformModule::MAX_TAGS_COUNT));
        }

        if ($this->farmRole->getFarm()->settings[Entity\FarmSetting::EC2_VPC_ID] != $this->orphaned->vpcId) {
            throw new ValidationErrorException(sprintf("Instance and Farm must correspond to the same VPC, but they differ: Farm: %s, Instance: %s.",
                $this->farmRole->getFarm()->settings[Entity\FarmSetting::EC2_VPC_ID],
                $this->orphaned->vpcId
            ));
        }

        if ($this->orphaned->subnetId && !in_array($this->orphaned->subnetId, json_decode($this->farmRole->settings[Entity\FarmRoleSetting::AWS_VPC_SUBNET_ID]))) {
            throw new ValidationErrorException(sprintf("Instance subnet '%s' must be enabled in FarmRole. Enabled subnets: %s.",
                $this->orphaned->subnetId,
                join(", ", json_decode($this->farmRole->settings[Entity\FarmRoleSetting::AWS_VPC_SUBNET_ID], true))
            ));
        }
    }

    /**
     * {@inheritdoc}
     * @see AbstractServerImport::importServer
     */
    protected function importServer()
    {
        $aws = $this->farmRole->getFarm()->getEnvironment()->aws($this->farmRole->cloudLocation);
        try {
            $instance = $this->instance = $aws->ec2->instance->describe($this->orphaned->cloudServerId)->get(0)->instancesSet->get(0);

            $this->server->properties[EC2_SERVER_PROPERTIES::AVAIL_ZONE] = $instance->placement->availabilityZone;
            $this->server->properties[EC2_SERVER_PROPERTIES::ARCHITECTURE] = $instance->architecture;
            $this->server->cloudLocationZone = $instance->placement->availabilityZone;
            $this->server->setOs($instance->platform ? $instance->platform : 'linux');

            $this->server->properties[EC2_SERVER_PROPERTIES::INSTANCE_ID] = $this->orphaned->cloudServerId;
            $this->server->properties[EC2_SERVER_PROPERTIES::VPC_ID] = $this->orphaned->vpcId;
            $this->server->properties[EC2_SERVER_PROPERTIES::SUBNET_ID] = $this->orphaned->subnetId;

            $this->server->properties[EC2_SERVER_PROPERTIES::REGION] = $this->server->cloudLocation;
            $this->server->properties[EC2_SERVER_PROPERTIES::AMIID] = $this->server->imageId;

            $this->server->instanceTypeName = $this->server->type;

            $p = PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2);
            $instanceTypeInfo = $p->getInstanceType($this->orphaned->instanceType, (new \Scalr_Environment())->loadById($this->server->envId), $this->server->cloudLocation);
            $this->server->properties[Entity\Server::INFO_INSTANCE_VCPUS] = isset($instanceTypeInfo['vcpus']) ? $instanceTypeInfo['vcpus'] : null;
        } catch (Exception $e) {
            throw new ServerImportException(sprintf('Scalr was unable to retrieve details for instance %s: %s', $this->orphaned->cloudServerId, $e->getMessage()), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     * @see AbstractServerImport::applyTags
     */
    protected function applyTags()
    {
        if (empty($this->instance)) {
            throw new ServerImportException("Instance property is empty. Cannot add tags to server");
        }

        $dbServer = DBServer::LoadByID($this->server->serverId);

        try {
            // Invar: move applyGlobalVarsToValue to Entity\Server
            $tags = [[
                'key'   => Scalr_Governance::SCALR_META_TAG_NAME,
                'value' => $dbServer->applyGlobalVarsToValue(Scalr_Governance::SCALR_META_TAG_VALUE)
            ]];

            foreach ($this->tags as $key => $value) {
                $tags[] = [
                    'key' => $key,
                    'value' => $dbServer->applyGlobalVarsToValue($value)
                ];
            }

            $this->instance->createTags($tags);
        } catch (Exception $e) {
            throw new ServerImportException(sprintf('Scalr was unable to add tags to server %s: %s', $this->server->serverId, $e->getMessage()), $e->getCode(), $e);
        }
    }
}
