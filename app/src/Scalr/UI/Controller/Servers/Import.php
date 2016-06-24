<?php

use Scalr\Acl\Acl;
use Scalr\Model\Entity;
use Scalr\UI\Request\JsonData;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\OrphanedServer;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;

/**
 * Import non-scalarizr servers
 */
class Scalr_UI_Controller_Servers_Import extends Scalr_UI_Controller
{
    /**
     * List of allowed platforms
     *
     * @var array
     */
    protected $allowedPlatforms = [
        SERVER_PLATFORMS::EC2
    ];

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess()
            && $this->request->isAllowed(Acl::RESOURCE_DISCOVERY_SERVERS)
            && $this->request->isAllowed(Acl::RESOURCE_DISCOVERY_SERVERS, Acl::PERM_DISCOVERY_SERVERS_IMPORT);
    }

    /**
     * @param   string  $platform       Name of platform
     * @param   string  $cloudLocation  Name of location
     * @param   array   $ids            The list of the identifiers of the cloud instances
     * @param   int     $roleId         optional    Identifier of Role
     * @return  array
     * @throws  Exception
     */
    public function checkStatus($platform, $cloudLocation, $ids, $roleId = null)
    {
        if (!in_array($platform, $this->allowedPlatforms)) {
            throw new Exception(sprintf("Platform '%s' is not supported", $platform));
        }

        if (!$this->environment->isPlatformEnabled($platform)) {
            throw new Exception(sprintf("Platform '%s' is not enabled", $platform));
        }

        if (empty($ids) || empty($ids[0])) {
            throw new Exception("You should provide at least one instanceId");
        }

        $instances = PlatformFactory::NewPlatform($platform)->getOrphanedServers($this->getEnvironmentEntity(), $cloudLocation, $ids);
        $status = ['instances' => $instances];

        $imageIds = array_unique(array_map(function ($item) {
            return $item->imageId;
        }, $instances));

        if (count($imageIds) != 1) {
            $status['compatibility'] = ['success' => false];
            return $status;
        }

        /* @var $instance OrphanedServer */
        $instance = $instances[0];
        $status['compatibility'] = ['success' => true];

        // Check vpc compatibility
        if ($instance->vpcId && $instance->subnetId) {
            $gov = new Scalr_Governance($this->getEnvironmentId());
            $vpcGovernanceRegions = $gov->getValue(SERVER_PLATFORMS::EC2, Scalr_Governance::AWS_VPC, 'regions');
            if (isset($vpcGovernanceRegions)) {
                if (!array_key_exists($cloudLocation, $vpcGovernanceRegions)) {
                    $status['compatibility']['success'] = false;
                    $status['compatibility']['message'] = sprintf('Region <b>%s</b> is not allowed by the Governance.', $cloudLocation);
                } else {
                    $vpcGovernanceIds = $vpcGovernanceRegions[$cloudLocation]['ids'];
                    if (!empty($vpcGovernanceIds) && !in_array($instance->vpcId, $vpcGovernanceIds)) {
                        $status['compatibility']['success'] = false;
                        $status['compatibility']['message'] = sprintf('VPC <b>%s</b> is not allowed by the Governance.', $instance->vpcId);
                    } else {
                        $vpcGovernanceIds = $gov->getValue(SERVER_PLATFORMS::EC2, Scalr_Governance::AWS_VPC, 'ids');
                        /* @var $platformObject Ec2PlatformModule */
                        $platformObject = PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2);
                        $subnet = $platformObject->listSubnets($this->getEnvironment(), $cloudLocation, $instance->vpcId, true, $instance->subnetId);
                        if (isset($vpcGovernanceIds[$instance->vpcId])) {
                            if (!empty($vpcGovernanceIds[$instance->vpcId]) && is_array($vpcGovernanceIds[$instance->vpcId]) && !in_array($instance->subnetId, $vpcGovernanceIds[$instance->vpcId])) {
                                $status['compatibility']['success'] = false;
                                $status['compatibility']['message'] = sprintf('Subnet <b>%s</b> is prohibited by the Governance.', $instance->subnetId);
                            } else if ($vpcGovernanceIds[$instance->vpcId] == "outbound-only" && $subnet['type'] != 'private' ) {
                                $status['compatibility']['success'] = false;
                                $status['compatibility']['message'] = 'Only private subnets are allowed by the Governance.';
                            } else if ($vpcGovernanceIds[$instance->vpcId] == "full" && $subnet['type'] != 'public' ) {
                                $status['compatibility']['success'] = false;
                                $status['compatibility']['message'] = 'Only public subnets are allowed by the Governance.';
                            }
                        }
                    }
                }
            }
        }

        if (!$status['compatibility']['success']) {
            return $status;
        }

        $scopeCriteria = ['$or' => [
            ['accountId' => null],
            ['$and' => [
                ['accountId' => $this->getUser()->accountId],
                ['$or' => [[
                    'envId' => null
                ], [
                    'envId' => $this->getEnvironment()->id
                ]]]
            ]]
        ]];

        /* @var $image Entity\Image */
        $image = Entity\Image::findOne([
            ['platform' => $platform],
            ['cloudLocation' => $cloudLocation],
            ['id' => $instance->imageId],
            $scopeCriteria
        ]);

        $status['image'] = ['success' => !!$image, 'data' => ['id' => $instance->imageId]];
        if ($image) {
            if ($image->isScalarized) {
                $status['image']['success'] = false;
                $status['image']['isScalarized'] = true;
                return $status;
            }

            $status['image']['data'] = [
                'hash'  => $image->hash,
                'name'  => $image->name,
                'id'    => $image->id,
                'scope' => $image->getScope()
            ];

            $criteria = [['platform' => $platform], ['imageId' => $image->id]];
            if (!($platform == SERVER_PLATFORMS::GCE || $platform == SERVER_PLATFORMS::AZURE)) {
                $criteria[] = ['cloudLocation' => $cloudLocation];
            }
            $roleIds = [];

            foreach (Entity\RoleImage::find($criteria) as $ri) {
                $roleIds[] = $ri->roleId;
            }

            if (count($roleIds)) {
                $roles = Entity\Role::find([
                    ['id' => ['$in' => $roleIds]],
                    ['isScalarized' => false],
                    $scopeCriteria
                ]);

                $status['role'] = [
                    'availableRoles' => [],
                    'image' => Scalr_UI_Controller_Images::controller()->convertEntityToArray($image)
                ];

                $selectedRole = null;

                if (count($roles) == 1) {
                    $selectedRole = $roles->current();
                } else if ($roleId && in_array($roleId, $roleIds)) {
                    foreach ($roles as $role) {
                        /* @var $role Entity\Role */
                        if ($role->id == $roleId) {
                            $selectedRole = $role;
                            break;
                        }
                    }
                }

                foreach ($roles as $role) {
                    /* @var $role Entity\Role */
                    $status['role']['availableRoles'][] = [
                        'id' => $role->id,
                        'name' => $role->name,
                        'scope' => $role->getScope()
                    ];
                }

                if ($selectedRole) {
                    $status['role']['success'] = true;
                    $status['role']['data'] = [
                        'id'    => $selectedRole->id,
                        'name'  => $selectedRole->name,
                        'scope' => $selectedRole->getScope()
                    ];

                    $farms = [];
                    $status['farmrole'] = [
                        'instance' => [
                            'instanceType' => $instance->instanceType,
                            'vpcId' => $instance->vpcId,
                            'subnetId' => $instance->subnetId,
                            'roleName' => $selectedRole->name
                        ]
                    ];

                    foreach (Entity\Farm::find([['envId' => $this->getEnvironment()->id], ['status' => FARM_STATUS::RUNNING]]) as $farm) {
                        /* @var $farm Entity\Farm */
                        if ($this->request->hasPermissions($farm, Acl::PERM_FARMS_UPDATE) && $this->request->hasPermissions($farm, Acl::PERM_FARMS_SERVERS)) {
                            // cloud specific (EC2)
                            if ($farm->settings[Entity\FarmSetting::EC2_VPC_ID] == $instance->vpcId) {
                                $farms[$farm->id] = [
                                    'id' => $farm->id,
                                    'name' => $farm->name,
                                    'farmroles' => []
                                ];
                            }
                        }
                    }

                    foreach (Entity\FarmRole::find([['farmId' => ['$in' => array_keys($farms)]], ['roleId' => $selectedRole->id]]) as $farmRole) {
                        /* @var $farmRole Entity\FarmRole */
                        if (isset($farms[$farmRole->farmId])) {
                            if (!$instance->subnetId || $instance->subnetId && in_array($instance->subnetId, json_decode($farmRole->settings[Entity\FarmRoleSetting::AWS_VPC_SUBNET_ID]))) {
                                $farms[$farmRole->farmId]['farmroles'][] = [
                                    'id'    => $farmRole->id,
                                    'name'  => $farmRole->alias,
                                    'tags'  => $farmRole->getCloudTags(true)
                                ];
                            }
                        }
                    }

                    $status['farmrole']['data'] = array_values($farms);
                    $status['farmrole']['success'] = false;
                } else if (count($roles) > 1) {
                    $status['role']['success'] = false;
                } else {
                    $status['role']['success'] = false;
                }
            } else {
                $status['role']['success'] = false;
                $status['role']['image'] = Scalr_UI_Controller_Images::controller()->convertEntityToArray($image);
            }
        }

        return $status;
    }

    /**
     * @param   string      $platform
     * @param   string      $cloudLocation
     * @param   JsonData    $ids
     * @param   int         $roleId     optional
     * @throws  Exception
     */
    public function xCheckStatusAction($platform, $cloudLocation, JsonData $ids, $roleId = null)
    {
        $this->response->data(['status' => $this->checkStatus($platform, $cloudLocation, (array) $ids, $roleId)]);
    }

    /**
     * @param   string      $platform       Name of platform
     * @param   string      $instanceId     The identifier of the cloud instance
     * @param   int         $farmRoleId     The identifier of farmRole
     * @param   JsonData    $tags           Additional tags
     * @throws  Exception
     */
    public function xImportAction($platform, $instanceId, $farmRoleId, JsonData $tags)
    {
        if (!in_array($platform, $this->allowedPlatforms)) {
            throw new Exception(sprintf("Platform '%s' is not supported", $platform));
        }

        if (!$this->environment->isPlatformEnabled($platform)) {
            throw new Exception(sprintf("Platform '%s' is not enabled", $platform));
        }

        if (empty($instanceId)) {
            throw new Exception('InstanceId cannot be empty');
        }

        /* @var $farmRole Entity\FarmRole */
        if (!$farmRoleId || !($farmRole = Entity\FarmRole::findPk($farmRoleId))) {
            throw new Exception('FarmRole was not found');
        }

        $this->request->checkPermissions($farmRole, true);
        $this->request->checkPermissions($farmRole->getFarm(), Acl::PERM_FARMS_UPDATE);
        $this->request->checkPermissions($farmRole->getFarm(), Acl::PERM_FARMS_SERVERS);

        $farmRole->settings[Entity\FarmRoleSetting::SCALING_ENABLED] = 0;
        $farmRole->settings->save();

        $farmRole->getServerImport($this->getUser())->import($instanceId, (array) $tags);
        $this->response->success();
    }

    /**
     * @param   string      $platform
     * @param   string      $cloudLocation
     * @param   JsonData    $ids
     * @param   int         $roleId     optional
     * @throws  Exception
     */
    public function defaultAction($platform, $cloudLocation, JsonData $ids, $roleId = null)
    {
        $status = $this->checkStatus($platform, $cloudLocation, (array) $ids, $roleId);

        $this->response->page(['ui/servers/import/view.js'], [
            'allowedPlatforms'  => $this->allowedPlatforms,
            'status'            => $status
        ]);
    }
}
