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
     * @return  \ArrayObject Returns array looks like array(
     *                resource_id => array(name, description, resourceGroup, [array(permission_id => description)]))
     *                Third value of array is optional and determines unique permissions for specified
     *                resource which can be allowed or forbidden separately.
     */
    public static function getAll($raw = false)
    {
        $allows = 'Allows ';

        if (!isset(self::$list)) {
            self::$rawList = array(
                //resource_id => array(name, description, resourceGroup, [array(pemission_id => description)])
                Acl::RESOURCE_FARMS => array(
                    'Farms',
                    $allows . 'access to farm designer.',
                    Acl::GROUP_FARMS,
                    array(
                        //permission_id must be in the lowercase and less than 64 characters.
                        Acl::PERM_FARMS_MANAGE    => $allows . 'to manage (create/configure/delete) farms.',
                        Acl::PERM_FARMS_CLONE     => $allows . 'to clone farms.',
                        Acl::PERM_FARMS_LAUNCH    => $allows . 'to launch farms.',
                        Acl::PERM_FARMS_TERMINATE => $allows . 'to terminate farms.',
                        Acl::PERM_FARMS_NOT_OWNED_FARMS => $allows . 'to manage not owned farms.'
                    )
                ),

                Acl::RESOURCE_FARMS_ALERTS => array(
                    'Alerts',
                    $allows . 'access to alerts.',
                    Acl::GROUP_FARMS
                ),

                Acl::RESOURCE_FARMS_SERVERS => array(
                    'Servers',
                    $allows . 'access to servers.',
                    Acl::GROUP_FARMS
                ),

                Acl::RESOURCE_FARMS_EVENTS_AND_NOTIFICATIONS => array(
                    'Events and notifications',
                    $allows . 'access to events and notifications.',
                    Acl::GROUP_FARMS
                ),

                Acl::RESOURCE_FARMS_STATISTICS => array(
                    'Statistics',
                    $allows . 'access to statistics.',
                    Acl::GROUP_FARMS
                ),

                Acl::RESOURCE_FARMS_ROLES => array(
                    'Roles',
                    $allows . 'access to roles.',
                    Acl::GROUP_FARMS,
                    array(
                        Acl::PERM_FARMS_ROLES_CREATE      => $allows . 'to create (build/import) roles.',
                        Acl::PERM_FARMS_ROLES_MANAGE      => $allows . 'to manage (edit/delete) roles.',
                        Acl::PERM_FARMS_ROLES_CLONE       => $allows . 'to clone roles.',
                        Acl::PERM_FARMS_ROLES_BUNDLETASKS => $allows . 'to bundle tasks (role creation process logs).',
                    )
                ),

                Acl::RESOURCE_FARMS_SCRIPTS => array(
                    'Scripts',
                    $allows . 'access to scripts.',
                    Acl::GROUP_FARMS,
                    array(
                        Acl::PERM_FARMS_SCRIPTS_MANAGE    => $allows . 'to manage (create/edit/delete) scripts.',
                        Acl::PERM_FARMS_SCRIPTS_EXECUTE   => $allows . 'to execute scripts.',
                        Acl::PERM_FARMS_SCRIPTS_FORK      => $allows . 'to fork scripts.',
                    )
                ),

                Acl::RESOURCE_CLOUDSTACK_VOLUMES => array(
                    'Volumes',
                    $allows . 'access to CloudStack volumes.',
                    Acl::GROUP_CLOUDSTACK
                ),

                Acl::RESOURCE_CLOUDSTACK_SNAPSHOTS => array(
                    'Snapshots',
                    $allows . 'access to CloudStack snapshots.',
                    Acl::GROUP_CLOUDSTACK
                ),

                Acl::RESOURCE_CLOUDSTACK_PUBLIC_IPS => array(
                    'Public IPs',
                    $allows . 'access to CloudStack public IPs.',
                    Acl::GROUP_CLOUDSTACK
                ),

                Acl::RESOURCE_OPENSTACK_VOLUMES => array(
                    'Volumes',
                    $allows . 'access to OpenStack volumes.',
                    Acl::GROUP_OPENSTACK
                ),

                Acl::RESOURCE_OPENSTACK_SNAPSHOTS => array(
                    'Snapshots',
                    $allows . 'access to OpenStack snapshots.',
                    Acl::GROUP_OPENSTACK
                ),

                Acl::RESOURCE_OPENSTACK_PUBLIC_IPS => array(
                    'Public IPs',
                    $allows . 'access to OpenStack public IPs.',
                    Acl::GROUP_OPENSTACK
                ),

                Acl::RESOURCE_AWS_CLOUDWATCH => array(
                    'CloudWatch',
                    $allows . 'access to AWS CloudWatch.',
                    Acl::GROUP_AWS
                ),

                Acl::RESOURCE_AWS_ELASTIC_IPS => array(
                    'Elastic IPs',
                    $allows . 'access to AWS Elastic IPs.',
                    Acl::GROUP_AWS
                ),

                Acl::RESOURCE_AWS_ELB => array(
                    'Elastic Load Balancing (ELB)',
                    $allows . 'access to AWS Elastic Load Balancing.',
                    Acl::GROUP_AWS
                ),

                Acl::RESOURCE_AWS_IAM => array(
                    'Identity and Access Management (IAM)',
                    $allows . 'access to AWS Identity and Access Management.',
                    Acl::GROUP_AWS
                ),

                Acl::RESOURCE_AWS_RDS => array(
                    'Relational Database Service (RDS)',
                    $allows . 'access to Amazon Relational Database Service.',
                    Acl::GROUP_AWS
                ),

                Acl::RESOURCE_AWS_SNAPSHOTS => array(
                    'Snapshots',
                    $allows . 'access to AWS snapshots.',
                    Acl::GROUP_AWS
                ),

                Acl::RESOURCE_AWS_VOLUMES => array(
                    'Volumes',
                    $allows . 'access to AWS Volumes.',
                    Acl::GROUP_AWS
                ),

                Acl::RESOURCE_SECURITY_AWS_SECURITY_GROUPS => array(
                    'AWS security groups',
                    $allows . 'access to AWS security groups.',
                    Acl::GROUP_SECURITY
                ),

                Acl::RESOURCE_SECURITY_RETRIEVE_WINDOWS_PASSWORDS => array(
                    'Retrieve Windows passwords',
                    $allows . 'access to retrieve passwords for windows.',
                    Acl::GROUP_SECURITY
                ),

                Acl::RESOURCE_SECURITY_SSH_KEYS => array(
                    'SSH keys',
                    $allows . 'access to SSH keys.',
                    Acl::GROUP_SECURITY
                ),

                Acl::RESOURCE_LOGS_API_LOGS => array(
                    'API logs',
                    $allows . 'access to API logs.',
                    Acl::GROUP_LOGS
                ),

                Acl::RESOURCE_LOGS_SCRIPTING_LOGS => array(
                    'Scripting logs',
                    $allows . 'access to scripting logs.',
                    Acl::GROUP_LOGS
                ),

                Acl::RESOURCE_LOGS_SYSTEM_LOGS => array(
                    'System logs',
                    $allows . 'access to system logs.',
                    Acl::GROUP_LOGS
                ),

                Acl::RESOURCE_SERVICES_APACHE => array(
                    'Apache',
                    $allows . 'access to apache.',
                    Acl::GROUP_SERVICES
                ),

                Acl::RESOURCE_SERVICES_CHEF => array(
                    'Chef',
                    $allows . 'access to chef.',
                    Acl::GROUP_SERVICES
                ),

                Acl::RESOURCE_SERVICES_SSL => array(
                    'SSL',
                    $allows . 'access to SSL.',
                    Acl::GROUP_SERVICES
                ),

                Acl::RESOURCE_SERVICES_RABBITMQ => array(
                    'RabbitMQ',
                    $allows . 'access to RabbitMQ.',
                    Acl::GROUP_SERVICES
                ),

                Acl::RESOURCE_GENERAL_CUSTOM_EVENTS => array(
                    'Custom events',
                    $allows . 'access to custom events.',
                    Acl::GROUP_GENERAL
                ),

                Acl::RESOURCE_GENERAL_CUSTOM_SCALING_METRICS => array(
                    'Custom scaling metrics',
                    $allows . 'access to custom scaling metrics.',
                    Acl::GROUP_GENERAL
                ),

                Acl::RESOURCE_GENERAL_GLOBAL_VARIABLES => array(
                    'Global variables (environment level)',
                    $allows . 'access to global variables of environment level.',
                    Acl::GROUP_GENERAL
                ),

                Acl::RESOURCE_GENERAL_SCHEDULERTASKS => array(
                    'Tasks scheduler',
                    $allows . 'access to tasks scheduler.',
                    Acl::GROUP_GENERAL
                ),

                Acl::RESOURCE_DB_BACKUPS => array(
                    'Backups',
                    $allows . 'access to backups.',
                    Acl::GROUP_DATABASES,
                    array(
                        Acl::PERM_DB_BACKUPS_REMOVE => $allows . 'to remove database backups.',
                    )
                ),

                Acl::RESOURCE_DB_DATABASE_STATUS => array(
                    'Database status',
                    $allows . 'access to database status.',
                    Acl::GROUP_DATABASES,
                    array(
                        Acl::PERM_DB_DATABASE_STATUS_PMA => $allows . 'access to PMA.',
                    )
                ),

                Acl::RESOURCE_DB_SERVICE_CONFIGURATION => array(
                    'Service configuration',
                    $allows . 'access to service configuration.',
                    Acl::GROUP_DATABASES
                ),

                Acl::RESOURCE_DEPLOYMENTS_APPLICATIONS => array(
                    'Applications',
                    $allows . 'access to applications.',
                    Acl::GROUP_DEPLOYMENTS
                ),

                Acl::RESOURCE_DEPLOYMENTS_SOURCES => array(
                    'Sources',
                    $allows . 'access to sources.',
                    Acl::GROUP_DEPLOYMENTS
                ),

                Acl::RESOURCE_DEPLOYMENTS_TASKS => array(
                    'Tasks',
                    $allows . 'access to tasks.',
                    Acl::GROUP_DEPLOYMENTS
                ),

                Acl::RESOURCE_DNS_ZONES => array(
                    'Zones',
                    $allows . 'access to DNS zones.',
                    Acl::GROUP_DNS
                ),

                Acl::RESOURCE_ADMINISTRATION_BILLING => array(
                    'Billing',
                    $allows . 'access to billing.',
                    Acl::GROUP_ADMINISTRATION
                ),

                Acl::RESOURCE_ADMINISTRATION_GOVERNANCE => array(
                    'Governance',
                    $allows . 'access to governance.',
                    Acl::GROUP_ADMINISTRATION
                ),

                Acl::RESOURCE_ADMINISTRATION_ENV_CLOUDS => array(
                    'Setup clouds',
                    $allows . 'to manage cloud credentials for environments in which this user is a team member',
                    Acl::GROUP_ADMINISTRATION
                ),

                // ... add more resources here
            );

            //Removes disabled resources
            foreach (Acl::getDisabledResources() as $resourceId) {
                if (isset(self::$rawList[$resourceId])) {
                    unset(self::$rawList[$resourceId]);
                }
            }

            //Initializes set of the resources
            self::$list = new \ArrayObject(array());
            self::$idx = array();
            foreach (self::$rawList as $resourceId => $optionsArray) {
                $resourceDefinition = new ResourceObject($resourceId, $optionsArray);
                self::$list[$resourceId] = $resourceDefinition;
                if (!isset(self::$idx[$resourceDefinition->getGroup()])) {
                    self::$idx[$resourceDefinition->getGroup()] = array();
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
        $ret = new \ArrayObject(array());
        if (isset(self::$idx[$group])) {
            foreach (self::$idx[$group] as $resourceId) {
                $ret[$resourceId] = $list[$resourceId];
            }
        }
        return $ret;
    }
}