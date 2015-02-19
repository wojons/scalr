<?php

namespace Scalr\Acl\Resource;

use Scalr\Acl\Acl;

/**
 * Resource Definition class
 *
 * This class describes all known resources.
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    30.07.2013
 */
class Definition
{

    /**
     * Cache of the predefined resources raw
     *
     * @var array
     */
    private static $rawList;

    /**
     * Cache of the predefined resources
     *
     * @var array
     */
    private static $list;

    /**
     * Grouped resources
     *
     * @var array
     */
    private static $idx;

    /**
     * Gets all Resources
     *
     * This method describes all available resources
     *
     * @return  \ArrayObject Returns array looks like [
     *                resource_id => [name, description, resourceGroup, [[permission_id => description)]]]
     *                Third value of array is optional and determines unique permissions for specified
     *                resource which can be allowed or forbidden separately.
     */
    public static function getAll($raw = false)
    {
        $allows = 'Allows ';

        if (!isset(self::$list)) {
            self::$rawList = [
                //resource_id => [name, description, resourceGroup, [[pemission_id => description)])
                Acl::RESOURCE_FARMS => [
                    'Farms',
                    $allows . 'access to farm designer.',
                    Acl::GROUP_FARMS,
                    [
                        //permission_id must be in the lowercase and less than 64 characters.
                        Acl::PERM_FARMS_MANAGE    => $allows . 'to manage (create/configure/delete) farms.',
                        Acl::PERM_FARMS_CLONE     => $allows . 'to clone farms.',
                        Acl::PERM_FARMS_LAUNCH    => $allows . 'to launch farms.',
                        Acl::PERM_FARMS_TERMINATE => $allows . 'to terminate farms.',
                        Acl::PERM_FARMS_NOT_OWNED_FARMS => $allows . 'to manage not owned farms.'
                    ]
                ],

                Acl::RESOURCE_FARMS_ALERTS => [
                    'Alerts',
                    $allows . 'access to alerts.',
                    Acl::GROUP_FARMS
                ],

                Acl::RESOURCE_FARMS_SERVERS => [
                    'Servers',
                    $allows . 'access to servers.',
                    Acl::GROUP_FARMS,
                    [
                        Acl::PERM_FARMS_SERVERS_SSH_CONSOLE => $allows . 'to use server SSH Launcher.'
                    ]
                ],

                Acl::RESOURCE_FARMS_STATISTICS => [
                    'Statistics',
                    $allows . 'access to statistics.',
                    Acl::GROUP_FARMS
                ],

                Acl::RESOURCE_FARMS_ROLES => [
                    'Roles',
                    $allows . 'access to roles.',
                    Acl::GROUP_FARMS,
                    [
                        Acl::PERM_FARMS_ROLES_CREATE      => $allows . 'to create (build/import) roles.',
                        Acl::PERM_FARMS_ROLES_MANAGE      => $allows . 'to manage (edit/delete) roles.',
                        Acl::PERM_FARMS_ROLES_CLONE       => $allows . 'to clone roles.',
                        Acl::PERM_FARMS_ROLES_BUNDLETASKS => $allows . 'to bundle tasks (role creation process logs).',
                    ]
                ],

                Acl::RESOURCE_FARMS_IMAGES => [
                    'Images',
                    $allows . 'access to images.',
                    Acl::GROUP_FARMS,
                    [
                        Acl::PERM_FARMS_ROLES_CREATE      => $allows . 'to create (build/import) images.',
                        Acl::PERM_FARMS_ROLES_MANAGE      => $allows . 'to manage (edit/delete) images.'
                    ]
                ],

                Acl::RESOURCE_GCE_STATIC_IPS => [
                    'Static IPs',
                    $allows . 'access to GCE static IPs.',
                    Acl::GROUP_GCE
                ],

                Acl::RESOURCE_GCE_PERSISTENT_DISKS => [
                    'Persistent disks',
                    $allows . 'access to GCE persistent disks.',
                    Acl::GROUP_GCE
                ],

                Acl::RESOURCE_GCE_SNAPSHOTS => [
                    'Snapshots',
                    $allows . 'access to GCE snapshots.',
                    Acl::GROUP_GCE
                ],

                Acl::RESOURCE_CLOUDSTACK_VOLUMES => [
                    'Volumes',
                    $allows . 'access to CloudStack volumes.',
                    Acl::GROUP_CLOUDSTACK
                ],

                Acl::RESOURCE_CLOUDSTACK_SNAPSHOTS => [
                    'Snapshots',
                    $allows . 'access to CloudStack snapshots.',
                    Acl::GROUP_CLOUDSTACK
                ],

                Acl::RESOURCE_CLOUDSTACK_PUBLIC_IPS => [
                    'Public IPs',
                    $allows . 'access to CloudStack public IPs.',
                    Acl::GROUP_CLOUDSTACK
                ],

                Acl::RESOURCE_OPENSTACK_VOLUMES => [
                    'Volumes',
                    $allows . 'access to OpenStack volumes.',
                    Acl::GROUP_OPENSTACK
                ],

                Acl::RESOURCE_OPENSTACK_SNAPSHOTS => [
                    'Snapshots',
                    $allows . 'access to OpenStack snapshots.',
                    Acl::GROUP_OPENSTACK
                ],

                Acl::RESOURCE_OPENSTACK_PUBLIC_IPS => [
                    'Public IPs',
                    $allows . 'access to OpenStack public IPs.',
                    Acl::GROUP_OPENSTACK
                ],

                Acl::RESOURCE_OPENSTACK_ELB => [
                    'Load Balancing (LBaaS)',
                    $allows . 'access to load balancing service.',
                    Acl::GROUP_OPENSTACK
                ],

                Acl::RESOURCE_AWS_S3 => [
                    'S3 and Cloudfront',
                    $allows . 'access to AWS S3 and Cloudfront.',
                    Acl::GROUP_AWS
                ],

                Acl::RESOURCE_AWS_CLOUDWATCH => [
                    'CloudWatch',
                    $allows . 'access to AWS CloudWatch.',
                    Acl::GROUP_AWS
                ],

                Acl::RESOURCE_AWS_ELASTIC_IPS => [
                    'Elastic IPs',
                    $allows . 'access to AWS Elastic IPs.',
                    Acl::GROUP_AWS
                ],

                Acl::RESOURCE_AWS_ELB => [
                    'Elastic Load Balancing (ELB)',
                    $allows . 'access to AWS Elastic Load Balancing.',
                    Acl::GROUP_AWS
                ],

                Acl::RESOURCE_AWS_IAM => [
                    'Identity and Access Management (IAM)',
                    $allows . 'access to AWS Identity and Access Management.',
                    Acl::GROUP_AWS
                ],

                Acl::RESOURCE_AWS_RDS => [
                    'Relational Database Service (RDS)',
                    $allows . 'access to Amazon Relational Database Service.',
                    Acl::GROUP_AWS
                ],

                Acl::RESOURCE_AWS_SNAPSHOTS => [
                    'Snapshots',
                    $allows . 'access to AWS snapshots.',
                    Acl::GROUP_AWS
                ],

                Acl::RESOURCE_AWS_VOLUMES => [
                    'Volumes',
                    $allows . 'access to AWS Volumes.',
                    Acl::GROUP_AWS
                ],

                Acl::RESOURCE_AWS_ROUTE53 => [
                    'Route53',
                    $allows . 'access to AWS Route53.',
                    Acl::GROUP_AWS
                ],

                Acl::RESOURCE_SECURITY_SECURITY_GROUPS => [
                    'Security groups',
                    $allows . 'access to security groups.',
                    Acl::GROUP_SECURITY
                ],

                Acl::RESOURCE_SECURITY_RETRIEVE_WINDOWS_PASSWORDS => [
                    'Retrieve Windows passwords',
                    $allows . 'access to retrieve passwords for windows.',
                    Acl::GROUP_SECURITY
                ],

                Acl::RESOURCE_SECURITY_SSH_KEYS => [
                    'SSH keys',
                    $allows . 'access to SSH keys.',
                    Acl::GROUP_SECURITY
                ],

                Acl::RESOURCE_LOGS_EVENT_LOGS => [
                    'Event Log',
                    $allows . 'access to the Event Log.',
                    Acl::GROUP_LOGS
                ],

                Acl::RESOURCE_LOGS_SYSTEM_LOGS => [
                    'System Log',
                    $allows . 'access to the System Log.',
                    Acl::GROUP_LOGS
                ],

                Acl::RESOURCE_LOGS_SCRIPTING_LOGS => [
                    'Scripting Log',
                    $allows . 'access to the Scripting Log.',
                    Acl::GROUP_LOGS
                ],

                Acl::RESOURCE_LOGS_API_LOGS => [
                    'API Log',
                    $allows . 'access to the API Log.',
                    Acl::GROUP_LOGS
                ],

                Acl::RESOURCE_SERVICES_APACHE => [
                    'Apache',
                    $allows . 'access to apache.',
                    Acl::GROUP_SERVICES
                ],

                Acl::RESOURCE_SERVICES_ENVADMINISTRATION_CHEF => [
                    'Chef (environment scope)',
                    $allows . 'to manage chef servers in the environment scope.',
                    Acl::GROUP_SERVICES
                ],

                Acl::RESOURCE_SERVICES_ADMINISTRATION_CHEF => [
                    'Chef (account scope)',
                    $allows . 'to manage chef servers in the account scope.',
                    Acl::GROUP_SERVICES
                ],

                Acl::RESOURCE_SERVICES_SSL => [
                    'SSL',
                    $allows . 'access to SSL.',
                    Acl::GROUP_SERVICES
                ],

                Acl::RESOURCE_SERVICES_RABBITMQ => [
                    'RabbitMQ',
                    $allows . 'access to RabbitMQ.',
                    Acl::GROUP_SERVICES
                ],

                Acl::RESOURCE_GENERAL_CUSTOM_EVENTS => [
                    'Custom events',
                    $allows . 'access to custom events.',
                    Acl::GROUP_GENERAL,
                    [
                        Acl::PERM_GENERAL_CUSTOM_EVENTS_FIRE => $allows . 'to fire custom events.'
                    ]
                ],

                Acl::RESOURCE_GENERAL_CUSTOM_SCALING_METRICS => [
                    'Custom scaling metrics',
                    $allows . 'access to custom scaling metrics.',
                    Acl::GROUP_GENERAL
                ],

                Acl::RESOURCE_GENERAL_SCHEDULERTASKS => [
                    'Tasks scheduler',
                    $allows . 'access to tasks scheduler.',
                    Acl::GROUP_GENERAL
                ],

                Acl::RESOURCE_DB_BACKUPS => [
                    'Backups',
                    $allows . 'access to backups.',
                    Acl::GROUP_DATABASES,
                    [
                        Acl::PERM_DB_BACKUPS_REMOVE => $allows . 'to remove database backups.',
                    ]
                ],

                Acl::RESOURCE_DB_DATABASE_STATUS => [
                    'Database status',
                    $allows . 'access to database status.',
                    Acl::GROUP_DATABASES,
                    [
                        Acl::PERM_DB_DATABASE_STATUS_PMA => $allows . 'access to PMA.',
                    ]
                ],

                Acl::RESOURCE_DB_SERVICE_CONFIGURATION => [
                    'Service configuration',
                    $allows . 'access to service configuration.',
                    Acl::GROUP_DATABASES
                ],

                Acl::RESOURCE_DEPLOYMENTS_APPLICATIONS => [
                    'Applications',
                    $allows . 'access to applications.',
                    Acl::GROUP_DEPLOYMENTS
                ],

                Acl::RESOURCE_DEPLOYMENTS_SOURCES => [
                    'Sources',
                    $allows . 'access to sources.',
                    Acl::GROUP_DEPLOYMENTS
                ],

                Acl::RESOURCE_DEPLOYMENTS_TASKS => [
                    'Tasks',
                    $allows . 'access to tasks.',
                    Acl::GROUP_DEPLOYMENTS
                ],

                Acl::RESOURCE_DNS_ZONES => [
                    'Zones',
                    $allows . 'access to DNS zones.',
                    Acl::GROUP_DNS
                ],

                Acl::RESOURCE_ADMINISTRATION_BILLING => [
                    'Billing',
                    $allows . 'access to billing.',
                    Acl::GROUP_ADMINISTRATION
                ],

                Acl::RESOURCE_ADMINISTRATION_ORCHESTRATION => [
                    'Orchestration (account scope)',
                    $allows . 'access to orchestration in the account scope.',
                    Acl::GROUP_ADMINISTRATION
                ],

                Acl::RESOURCE_ADMINISTRATION_GLOBAL_VARIABLES => [
                    'Global variables (account scope)',
                    $allows . 'access to global variables in the account scope.',
                    Acl::GROUP_ADMINISTRATION
                ],

                Acl::RESOURCE_ADMINISTRATION_SCRIPTS => [
                    'Scripts (account scope)',
                    $allows . 'access to scripts.',
                    Acl::GROUP_ADMINISTRATION,
                    [
                        Acl::PERM_ADMINISTRATION_SCRIPTS_MANAGE    => $allows . 'to manage (create/edit/delete) scripts.',
                        Acl::PERM_ADMINISTRATION_SCRIPTS_EXECUTE   => $allows . 'to execute scripts.',
                        Acl::PERM_ADMINISTRATION_SCRIPTS_FORK      => $allows . 'to fork scripts.',
                    ]
                ],

                Acl::RESOURCE_ADMINISTRATION_WEBHOOKS => [
                    'Webhooks (account scope)',
                    $allows . 'to manage webhooks in the account scope.',
                    Acl::GROUP_ADMINISTRATION
                ],

                Acl::RESOURCE_ENVADMINISTRATION_ENV_CLOUDS => [
                    'Setup clouds',
                    $allows . 'to manage cloud credentials for environments in which this user is a team member',
                    Acl::GROUP_ENVADMINISTRATION
                ],

                Acl::RESOURCE_ENVADMINISTRATION_GOVERNANCE => [
                    'Governance',
                    $allows . 'access to governance.',
                    Acl::GROUP_ENVADMINISTRATION
                ],

                Acl::RESOURCE_ENVADMINISTRATION_GLOBAL_VARIABLES => [
                    'Global variables (environment scope)',
                    $allows . 'access to global variables in the environment scope.',
                    Acl::GROUP_ENVADMINISTRATION
                ],

                Acl::RESOURCE_ENVADMINISTRATION_WEBHOOKS => [
                    'Webhooks (environment scope)',
                    $allows . 'to manage webhooks in the environment scope.',
                    Acl::GROUP_ENVADMINISTRATION
                ],

                Acl::RESOURCE_ANALYTICS_PROJECTS => [
                    'Cost Analytics Projects',
                    $allows . ' account users to create a new projects for cost analytics',
                    Acl::GROUP_ANALYTICS
                ],

                Acl::RESOURCE_ADMINISTRATION_ANALYTICS => [
                    'Cost Analytics (account scope)',
                    $allows . ' access to Cost Analytics in the account scope',
                    Acl::GROUP_ADMINISTRATION,
                    [
                        Acl::PERM_ADMINISTRATION_ANALYTICS_MANAGE_PROJECTS => $allows . 'to edit/create projects in the account scope.',
                        Acl::PERM_ADMINISTRATION_ANALYTICS_ALLOCATE_BUDGET => $allows . "to set/edit projects' budgets in the account scope.",
                    ]
                ],

                Acl::RESOURCE_ENVADMINISTRATION_ANALYTICS => [
                    'Cost Analytics (environment scope)',
                    $allows . ' access to Cost Analytics in the environment scope',
                    Acl::GROUP_ENVADMINISTRATION
                ],

                // ... add more resources here
            ];

            //Removes disabled resources
            foreach (Acl::getDisabledResources() as $resourceId) {
                if (isset(self::$rawList[$resourceId])) {
                    unset(self::$rawList[$resourceId]);
                }
            }

            //Initializes set of the resources
            self::$list = new \ArrayObject([]);
            self::$idx = [];
            foreach (self::$rawList as $resourceId => $optionsArray) {
                $resourceDefinition = new ResourceObject($resourceId, $optionsArray);
                self::$list[$resourceId] = $resourceDefinition;
                if (!isset(self::$idx[$resourceDefinition->getGroup()])) {
                    self::$idx[$resourceDefinition->getGroup()] = [];
                }
                self::$idx[$resourceDefinition->getGroup()][] = $resourceId;
            }
        }

        return $raw ? self::$rawList : self::$list;
    }

    /**
     * Gets the definition of the provided resource
     *
     * @param   int            $resourceId  The ID of the ACL resource
     * @return  ResourceObject Returns the object which describes specified resource or null if
     *                                     it does not exist.
     */
    public static function get($resourceId)
    {
        $list = self::getAll();

        return isset($list[$resourceId]) ? $list[$resourceId] : null;
    }

    /**
     * Checks if specified resource is defined
     *
     * @param   int   $resourceId  The ID of the ACL resource
     * @return  bool  Returns true if defined or false otherwise
     */
    public static function has($resourceId)
    {
        $list = self::getAll();

        return isset($list[$resourceId]);
    }

    /**
     * Gets the list of the resources of specified associative group.
     *
     * @param   string    $group The group identifier
     * @return  \ArrayObject Returns the list of the resources of the specified associative group.
     */
    public static function getByGroup($group)
    {
        $list = self::getAll();
        $ret = new \ArrayObject([]);
        if (isset(self::$idx[$group])) {
            foreach (self::$idx[$group] as $resourceId) {
                $ret[$resourceId] = $list[$resourceId];
            }
        }
        return $ret;
    }
}
