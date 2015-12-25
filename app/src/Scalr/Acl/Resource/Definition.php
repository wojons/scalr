<?php

namespace Scalr\Acl\Resource;

use Scalr\Acl\Acl;
use Scalr\Acl\Resource\Mode\CloudResourceScopeMode;

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
     *                resource_id => [name, description, resourceGroup, [[permission_id => description)][, ModeInterface]] ]
     *                Third value of array is optional and determines unique permissions for specified
     *                resource which can be allowed or forbidden separately.
     *                Forth value of the array is optional Resource Mode.
     */
    public static function getAll($raw = false)
    {
        $allows = 'Allows ';

        if (!isset(self::$list)) {
            self::$rawList = [
                //resource_id => [name, description, resourceGroup, [[pemission_id => description)])
                Acl::RESOURCE_CLOUD_CREDENTIALS_ENVIRONMENT => [
                    'Cloud credentials (environment scope)',
                    "{$allows}to manage cloud credentials in the environment scope",
                    Acl::GROUP_CLOUD_CREDENTIALS
                ],

                Acl::RESOURCE_CLOUD_CREDENTIALS_ACCOUNT => [
                    'Cloud credentials (account scope)',
                    "{$allows}to manage cloud credentials in the account scope",
                    Acl::GROUP_CLOUD_CREDENTIALS
                ],

                Acl::RESOURCE_FARMS => [
                    'All Farms',
                    $allows . 'access to farms and servers.',
                    Acl::GROUP_FARMS_SERVERS,
                    [
                        //permission_id must be in the lowercase and less than 64 characters.
                        Acl::PERM_FARMS_MANAGE              => $allows . 'to manage (create/configure/delete) farms.',
                        Acl::PERM_FARMS_CLONE               => $allows . 'to clone farms.',
                        Acl::PERM_FARMS_LAUNCH_TERMINATE    => $allows . 'to launch/terminate farms.',
                        Acl::PERM_FARMS_CHANGE_OWNERSHIP    => $allows . 'to change owner or team',
                        Acl::PERM_FARMS_SERVERS             => $allows . 'to manage servers',
                        Acl::PERM_FARMS_STATISTICS          => $allows . 'to access statistics'
                    ],
                    //Resource Mode object that must be instance of ModeInterface
                    null
                ],

                Acl::RESOURCE_TEAM_FARMS => [
                    'Farms Your Teams Own',
                    $allows . 'access to farms and servers.',
                    Acl::GROUP_FARMS_SERVERS,
                    [
                        //permission_id must be in the lowercase and less than 64 characters.
                        Acl::PERM_FARMS_MANAGE              => $allows . 'to manage (create/configure/delete) farms.',
                        Acl::PERM_FARMS_CLONE               => $allows . 'to clone farms.',
                        Acl::PERM_FARMS_LAUNCH_TERMINATE    => $allows . 'to launch/terminate farms.',
                        Acl::PERM_FARMS_CHANGE_OWNERSHIP    => $allows . 'to change owner or team',
                        Acl::PERM_FARMS_SERVERS             => $allows . 'to manage servers',
                        Acl::PERM_FARMS_STATISTICS          => $allows . 'to access statistics'
                    ]
                ],

                Acl::RESOURCE_OWN_FARMS => [
                    'Farms You Own',
                    $allows . 'access to farms and servers.',
                    Acl::GROUP_FARMS_SERVERS,
                    [
                        //permission_id must be in the lowercase and less than 64 characters.
                        Acl::PERM_FARMS_MANAGE              => $allows . 'to manage (create/configure/delete) farms.',
                        Acl::PERM_FARMS_CLONE               => $allows . 'to clone farms.',
                        Acl::PERM_FARMS_LAUNCH_TERMINATE    => $allows . 'to launch/terminate farms.',
                        Acl::PERM_FARMS_CHANGE_OWNERSHIP   => $allows . 'to change owner or team',
                        Acl::PERM_FARMS_SERVERS             => $allows . 'to manage servers',
                        Acl::PERM_FARMS_STATISTICS          => $allows . 'to access statistics'
                    ]
                ],

                Acl::RESOURCE_ROLES_ENVIRONMENT => [
                    'Roles',
                    $allows . 'access to roles.',
                    Acl::GROUP_ROLES_IMAGES,
                    [
                        Acl::PERM_ROLES_ENVIRONMENT_MANAGE      => $allows . 'to manage (edit/delete) roles.',
                        Acl::PERM_ROLES_ENVIRONMENT_CLONE       => $allows . 'to clone roles.',
                    ]
                ],

                Acl::RESOURCE_IMAGES_ENVIRONMENT => [
                    'Images',
                    $allows . 'access to images.',
                    Acl::GROUP_ROLES_IMAGES,
                    [
                        Acl::PERM_IMAGES_ENVIRONMENT_IMPORT      => $allows . 'to import images.',
                        Acl::PERM_IMAGES_ENVIRONMENT_BUILD       => $allows . 'to build images.',
                        Acl::PERM_IMAGES_ENVIRONMENT_MANAGE      => $allows . 'to manage (register/edit/delete) images.',
                        Acl::PERM_IMAGES_ENVIRONMENT_BUNDLETASKS => $allows . 'to bundle tasks (image creation process logs).',
                    ]
                ],

                Acl::RESOURCE_ROLES_ACCOUNT => [
                    'Roles',
                    $allows . 'access to roles in the account scope.',
                    Acl::GROUP_ACCOUNT,
                    [
                        Acl::PERM_ROLES_ACCOUNT_MANAGE      => $allows . 'to manage (edit/delete) roles.',
                        Acl::PERM_ROLES_ACCOUNT_CLONE       => $allows . 'to clone roles.'
                    ]
                ],

                Acl::RESOURCE_IMAGES_ACCOUNT => [
                    'Images',
                    $allows . 'access to images in the account scope.',
                    Acl::GROUP_ACCOUNT,
                    [
                        Acl::PERM_ROLES_ACCOUNT_MANAGE      => $allows . 'to manage (edit/delete) images.'
                    ]
                ],

                Acl::RESOURCE_GCE_STATIC_IPS => [
                    'Static IPs',
                    $allows . 'access to GCE static IPs.',
                    Acl::GROUP_GCE,
                    [
                        Acl::PERM_GCE_STATIC_IPS_MANAGE => $allows . 'to manage (edit/delete) static IPs.'
                    ]
                ],

                Acl::RESOURCE_GCE_PERSISTENT_DISKS => [
                    'Persistent disks',
                    $allows . 'access to GCE persistent disks.',
                    Acl::GROUP_GCE,
                    [
                        Acl::PERM_GCE_PERSISTENT_DISKS_MANAGE => $allows . 'to manage (edit/delete) persistent disks.'
                    ]
                ],

                Acl::RESOURCE_GCE_SNAPSHOTS => [
                    'Snapshots',
                    $allows . 'access to GCE snapshots.',
                    Acl::GROUP_GCE,
                    [
                        Acl::PERM_GCE_SNAPSHOTS_MANAGE => $allows . 'to manage (edit/delete) snapshots.'
                    ]
                ],

                Acl::RESOURCE_CLOUDSTACK_VOLUMES => [
                    'Volumes',
                    $allows . 'access to CloudStack volumes.',
                    Acl::GROUP_CLOUDSTACK,
                    [
                        Acl::PERM_CLOUDSTACK_VOLUMES_MANAGE => $allows . 'to manage (edit/delete) volumes.'
                    ]
                ],

                Acl::RESOURCE_CLOUDSTACK_SNAPSHOTS => [
                    'Snapshots',
                    $allows . 'access to CloudStack snapshots.',
                    Acl::GROUP_CLOUDSTACK,
                    [
                        Acl::PERM_CLOUDSTACK_SNAPSHOTS_MANAGE => $allows . 'to manage (edit/delete) snapshots.'
                    ]
                ],

                Acl::RESOURCE_CLOUDSTACK_PUBLIC_IPS => [
                    'Public IPs',
                    $allows . 'access to CloudStack public IPs.',
                    Acl::GROUP_CLOUDSTACK,
                    [
                        Acl::PERM_CLOUDSTACK_PUBLIC_IPS_MANAGE => $allows . 'to manage (edit/delete) public IPs.'
                    ]
                ],

                Acl::RESOURCE_OPENSTACK_VOLUMES => [
                    'Volumes',
                    $allows . 'access to OpenStack volumes.',
                    Acl::GROUP_OPENSTACK,
                    [
                        Acl::PERM_OPENSTACK_VOLUMES_MANAGE => $allows . 'to manage (edit/delete) volumes.'
                    ]
                ],

                Acl::RESOURCE_OPENSTACK_SNAPSHOTS => [
                    'Snapshots',
                    $allows . 'access to OpenStack snapshots.',
                    Acl::GROUP_OPENSTACK,
                    [
                        Acl::PERM_OPENSTACK_SNAPSHOTS_MANAGE => $allows . 'to manage (edit/delete) snapshots.'
                    ]
                ],

                Acl::RESOURCE_AWS_S3 => [
                    'S3 and Cloudfront',
                    $allows . 'access to AWS S3 and Cloudfront.',
                    Acl::GROUP_AWS,
                    [
                        Acl::PERM_AWS_S3_MANAGE => $allows . 'to manage (create/edit/delete) AWS S3 and Cloudfront resources.'
                    ]
                ],

                Acl::RESOURCE_AWS_CLOUDWATCH => [
                    'CloudWatch',
                    $allows . 'access to AWS CloudWatch.',
                    Acl::GROUP_AWS
                ],

                Acl::RESOURCE_AWS_ELASTIC_IPS => [
                    'Elastic IPs',
                    $allows . 'access to AWS Elastic IPs.',
                    Acl::GROUP_AWS,
                    [
                        Acl::PERM_AWS_ELASTIC_IPS_MANAGE => $allows . 'to manage (edit/delete) AWS Elastic IPs.'
                    ]
                ],

                Acl::RESOURCE_AWS_ELB => [
                    'Elastic Load Balancing (ELB)',
                    $allows . 'access to AWS Elastic Load Balancing.',
                    Acl::GROUP_AWS,
                    [
                        Acl::PERM_AWS_ELB_MANAGE => $allows . 'to manage (create/edit/delete) AWS Elastic Load Balancing.'
                    ]
                ],

                Acl::RESOURCE_AWS_IAM => [
                    'Identity and Access Management (IAM)',
                    $allows . 'access to AWS Identity and Access Management.',
                    Acl::GROUP_AWS,
                    [
                        Acl::PERM_AWS_IAM_MANAGE => $allows . 'to manage (create/edit/delete) AWS Identity and Access Management.'
                    ]
                ],

                Acl::RESOURCE_AWS_RDS => [
                    'Relational Database Service (RDS)',
                    $allows . 'access to Amazon Relational Database Service.',
                    Acl::GROUP_AWS,
                    [
                        Acl::PERM_AWS_RDS_MANAGE => $allows . 'to manage (create/edit/delete) Amazon Relational Database Service.'
                    ]
                ],

                Acl::RESOURCE_AWS_SNAPSHOTS => [
                    'Snapshots',
                    $allows . 'access to AWS snapshots.',
                    Acl::GROUP_AWS,
                    [
                        Acl::PERM_AWS_SNAPSHOTS_MANAGE => $allows . 'to manage (create/edit/delete) AWS snapshots.'
                    ]
                ],

                Acl::RESOURCE_AWS_VOLUMES => [
                    'Volumes',
                    $allows . 'access to AWS Volumes.',
                    Acl::GROUP_AWS,
                    [
                        Acl::PERM_AWS_VOLUMES_MANAGE => $allows . 'to manage (create/edit/delete) AWS Volumes.'
                    ],
                    new CloudResourceScopeMode(Acl::RESOURCE_AWS_VOLUMES, 'AWS Volumes'),
                ],

                Acl::RESOURCE_AWS_ROUTE53 => [
                    'Route53',
                    $allows . 'access to AWS Route53.',
                    Acl::GROUP_AWS,
                    [
                        Acl::PERM_AWS_ROUTE53_MANAGE => $allows . 'to manage (create/edit/delete) AWS Route53 resources.'
                    ]
                ],

                Acl::RESOURCE_SECURITY_SECURITY_GROUPS => [
                    'Security groups',
                    $allows . 'access to security groups.',
                    Acl::GROUP_SECURITY,
                    [
                        Acl::PERM_SECURITY_SECURITY_GROUPS_MANAGE => $allows . 'to manage (create/edit/delete) security groups.'
                    ]
                ],

                Acl::RESOURCE_SECURITY_RETRIEVE_WINDOWS_PASSWORDS => [
                    'Retrieve Windows passwords',
                    $allows . 'access to retrieve passwords for windows.',
                    Acl::GROUP_SECURITY
                ],

                Acl::RESOURCE_SECURITY_SSH_KEYS => [
                    'SSH keys',
                    $allows . 'access to SSH keys.',
                    Acl::GROUP_SECURITY,
                    [
                        Acl::PERM_SECURITY_SSH_KEYS_MANAGE => $allows . 'to manage (edit/delete) SSH keys.'
                    ]
                ],

                Acl::RESOURCE_LOGS_EVENT_LOGS => [
                    'Event Log',
                    $allows . 'access to the Event Log.',
                    Acl::GROUP_LOGS,

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
                    Acl::GROUP_SERVICES,
                    [
                        Acl::PERM_SERVICES_APACHE_MANAGE => $allows . 'to manage (create/edit/delete) virtual hosts.'
                    ]
                ],

                Acl::RESOURCE_SERVICES_CHEF_ENVIRONMENT => [
                    'Chef (environment scope)',
                    $allows . 'to view chef servers in the environment scope.',
                    Acl::GROUP_SERVICES,
                    [
                        Acl::PERM_SERVICES_CHEF_ENVIRONMENT_MANAGE => $allows . 'to manage (create/edit/delete) chef servers.'
                    ]
                ],

                Acl::RESOURCE_SERVICES_CHEF_ACCOUNT => [
                    'Chef (account scope)',
                    $allows . 'to view chef servers in the account scope.',
                    Acl::GROUP_SERVICES,
                    [
                        Acl::PERM_SERVICES_CHEF_ACCOUNT_MANAGE => $allows . 'to manage (create/edit/delete) chef servers.'
                    ]
                ],

                Acl::RESOURCE_SERVICES_SSL => [
                    'SSL',
                    $allows . 'access to SSL.',
                    Acl::GROUP_SERVICES,
                    [
                        Acl::PERM_SERVICES_SSL_MANAGE => $allows . 'to manage (create/edit/delete) SSL certificates.'
                    ]
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
                        Acl::PERM_GENERAL_CUSTOM_EVENTS_MANAGE => $allows . 'to manage (create/edit/delete) custom events.',
                        Acl::PERM_GENERAL_CUSTOM_EVENTS_FIRE => $allows . 'to fire custom events.'
                    ]
                ],

                Acl::RESOURCE_GENERAL_CUSTOM_SCALING_METRICS => [
                    'Custom scaling metrics',
                    $allows . 'access to custom scaling metrics.',
                    Acl::GROUP_GENERAL,
                    [
                        Acl::PERM_GENERAL_CUSTOM_SCALING_METRICS_MANAGE => $allows . 'to manage (create/edit/delete) custom scaling metrics.'
                    ]
                ],

                Acl::RESOURCE_GENERAL_SCHEDULERTASKS => [
                    'Tasks scheduler',
                    $allows . 'access to tasks scheduler.',
                    Acl::GROUP_GENERAL,
                    [
                        Acl::PERM_GENERAL_SCHEDULERTASKS_MANAGE => $allows . 'to manage (create/edit/delete) tasks scheduler.'
                    ]
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
                        Acl::PERM_DB_DATABASE_STATUS_PMA    => $allows . 'access to PMA.',
                        Acl::PERM_DB_DATABASE_STATUS_MANAGE => $allows . 'to manage database settings.',
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
                    Acl::GROUP_DNS,
                    [
                        Acl::PERM_DNS_ZONES_MANAGE => $allows . 'to manage (create/edit/delete) DNS zones.'
                    ]
                ],

                Acl::RESOURCE_BILLING_ACCOUNT => [
                    'Billing',
                    $allows . 'access to billing.',
                    Acl::GROUP_ACCOUNT
                ],

                Acl::RESOURCE_ORCHESTRATION_ACCOUNT => [
                    'Orchestration (account scope)',
                    $allows . 'access to orchestration in the account scope.',
                    Acl::GROUP_ACCOUNT,
                    [
                        Acl::PERM_ORCHESTRATION_ACCOUNT_MANAGE => $allows . 'to manage (create/edit/delete) orchestration rules.'
                    ]
                ],

                Acl::RESOURCE_GLOBAL_VARIABLES_ACCOUNT => [
                    'Global variables (account scope)',
                    $allows . 'access to global variables in the account scope.',
                    Acl::GROUP_ACCOUNT,
                    [
                        Acl::PERM_GLOBAL_VARIABLES_ACCOUNT_MANAGE => $allows . 'to manage (create/edit/delete) global variables.'
                    ]
                ],

                Acl::RESOURCE_SCRIPTS_ACCOUNT => [
                    'Scripts (account scope)',
                    $allows . 'access to scripts.',
                    Acl::GROUP_ACCOUNT,
                    [
                        Acl::PERM_SCRIPTS_ACCOUNT_MANAGE    => $allows . 'to manage (create/edit/delete) scripts.',
                        Acl::PERM_SCRIPTS_ACCOUNT_FORK      => $allows . 'to fork scripts.',
                    ]
                ],

                Acl::RESOURCE_SCRIPTS_ENVIRONMENT => [
                    'Scripts (environment scope)',
                    $allows . 'access to scripts.',
                    Acl::GROUP_ENVIRONMENT,
                    [
                        Acl::PERM_SCRIPTS_ENVIRONMENT_MANAGE    => $allows . 'to manage (create/edit/delete) scripts.',
                        Acl::PERM_SCRIPTS_ENVIRONMENT_FORK      => $allows . 'to fork scripts.',
                        Acl::PERM_SCRIPTS_ENVIRONMENT_EXECUTE   => $allows . 'to execute scripts.',
                    ]
                ],

                Acl::RESOURCE_WEBHOOKS_ACCOUNT => [
                    'Webhooks (account scope)',
                    $allows . 'to manage webhooks in the account scope.',
                    Acl::GROUP_ACCOUNT,
                    [
                        Acl::PERM_WEBHOOKS_ACCOUNT_MANAGE => $allows . 'to manage (create/edit/delete) webhooks.'
                    ]
                ],

                Acl::RESOURCE_ENV_CLOUDS_ENVIRONMENT => [
                    'Setup clouds',
                    $allows . 'to manage cloud credentials for environments in which this user is a team member',
                    Acl::GROUP_ENVIRONMENT
                ],

                Acl::RESOURCE_GOVERNANCE_ENVIRONMENT => [
                    'Governance',
                    $allows . 'access to governance.',
                    Acl::GROUP_ENVIRONMENT
                ],

                Acl::RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT => [
                    'Global variables (environment scope)',
                    $allows . 'access to global variables in the environment scope.',
                    Acl::GROUP_ENVIRONMENT,
                    [
                        Acl::PERM_GLOBAL_VARIABLES_ENVIRONMENT_MANAGE => $allows . 'to manage (create/edit/delete) global variables.'
                    ]
                ],

                Acl::RESOURCE_WEBHOOKS_ENVIRONMENT => [
                    'Webhooks (environment scope)',
                    $allows . 'to manage webhooks in the environment scope.',
                    Acl::GROUP_ENVIRONMENT,
                    [
                        Acl::PERM_WEBHOOKS_ENVIRONMENT_MANAGE => $allows . 'to manage (create/edit/delete) webhooks.'
                    ]
                ],

                Acl::RESOURCE_ANALYTICS_ACCOUNT => [
                    'Cost Analytics (account scope)',
                    $allows . ' access to Cost Analytics in the account scope',
                    Acl::GROUP_ACCOUNT,
                    [
                        Acl::PERM_ANALYTICS_ACCOUNT_MANAGE_PROJECTS => $allows . 'to edit/create projects in the account scope.',
                        Acl::PERM_ANALYTICS_ACCOUNT_ALLOCATE_BUDGET => $allows . "to set/edit projects' budgets in the account scope.",
                    ]
                ],

                Acl::RESOURCE_ANALYTICS_ENVIRONMENT => [
                    'Cost Analytics (environment scope)',
                    $allows . ' access to Cost Analytics in the environment scope',
                    Acl::GROUP_ENVIRONMENT
                ],

                Acl::RESOURCE_ORPHANED_SERVERS => [
                    'Orphaned servers',
                    $allows . ' to manage servers created outside of Scalr',
                    Acl::GROUP_ENVIRONMENT
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
