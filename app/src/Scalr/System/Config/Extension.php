<?php

namespace Scalr\System\Config;

use Scalr\Util\ClosureInvoker;
use IteratorAggregate;

/**
 * Extension
 *
 * This class helps to define default values of the config parameters.
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    11.06.2013
 */
class Extension implements IteratorAggregate
{
    /**
     * Array of defined parameters
     *
     * It looks like array('dot.notation.name' => object)
     *
     * @var array
     */
    private $parameters = array();

    /**
     * Array of defined parameter bags
     *
     * @var array
     */
    public $paths = array();

    /**
     * Loads config defaults
     *
     * It's supposed to load all external Extension from here.
     *
     * @return  Extension
     */
    public function load()
    {
        $this->parameters = array();

        //Please follow alphabetical order when add something new
        $this
            ->sub('scalr')
                ->node('allowed_clouds', array('ec2', 'openstack', 'cloudstack', 'idcf', 'gce', 'ocs', 'ecs',
                                               'rackspacenguk', 'rackspacengus', 'nebula'))

                ->sub('analytics', false)
                    ->node('enabled', false)
                    ->sub('connections')
                        ->sub('analytics')
                            ->node('driver', 'mysqli')
                            ->node('host')
                            ->node('port', null)
                            ->node('name', 'analytics')
                            ->node('user')
                            ->node('pass')
                        ->end()
                    ->end()
                ->end()

                ->sub('auditlog', false)
                    ->node('enabled', false)
                ->end()

                ->node('auth_mode')

                ->sub('aws')
                    ->node('security_group_name', 'scalr.ip-pool')
                    ->node('ip_pool', array())
                    ->node('security_group_prefix', 'scalr.')
                    ->node('use_proxy', false)
                    ->sub('plugins', false)
                        ->node('enabled', array())
                        ->sub('statistics', false)
                            ->node('storage_max_size', 268435456)
                        ->end()
                    ->end()
                ->end()

                ->sub('billing')
                    ->node('enabled')
                    ->node('chargify_api_key', '')
                    ->node('chargify_domain', '')
                    ->node('emergency_phone_number', '')
                ->end()

                ->sub('cloudyn', false)
                    ->node('master_email', '')
                    ->node('environment', 'PROD')
                ->end()

                ->sub('connections')
                    ->sub('proxy', false)
                        ->node('host', 'localhost')
                        ->node('port', 3128)
                        ->node('user', null)
                        ->node('pass', null)
                        ->node('type', 0)
                    ->end()
                    ->sub('ldap', false)
                        ->node('host', 'localhost')
                        ->node('port', null)
                        ->node('base_dn')
                        ->node('base_dn_groups', null)
                        ->node('user', null)
                        ->node('pass', null)
                        ->node('group_nesting', true)
                        ->node('domain', null)
                        ->node('bind_type', \Scalr\Net\Ldap\LdapClient::BIND_TYPE_REGULAR)
                        ->node('mail_attribute', null)
                        ->node('fullname_attribute', 'displayName')
                        ->node('username_attribute', 'sAMAccountName')
                        ->node('group_member_attribute', 'member')
                        ->node('group_member_attribute_type', 'regular')
                        ->node('groupname_attribute', 'sAMAccountName')
                        ->node('debug', false)
                        ->sub('filter', false)
                            ->node('users', '(&(objectCategory=person)(objectClass=user))')
                            ->node('groups', '(&(objectClass=group))')
                        ->end()
                    ->end()
                    ->sub('mysql')
                        ->node('driver', 'mysqli')
                        ->node('host', '127.0.0.1')
                        ->node('port', null)
                        ->node('name')
                        ->node('user')
                        ->node('pass')
                    ->end()
                    ->sub('mongodb', false)
                        ->node('driver', 'mongodb')
                        ->node('host', 'localhost')
                        ->node('port', 27017)
                        //name parameter is required only when parent bag is defined
                        ->node('name')
                    ->end()
                ->end()

                ->sub('cache', false)
                    ->sub('instance_types', false)
                        ->node('lifetime', 86400)
                    ->end()
                ->end()

                ->sub('crontab', false)
                    ->node('log', '/dev/null')
                    ->node('log_level', 'ERROR')
                    ->sub('heartbeat', false)
                      ->node('delay', 18000)
                      ->node('liveness', 3)
                    ->end()
                    ->sub('sockets', false)
                        ->node('broker', 'ipc://' . sys_get_temp_dir() . '/' . SCALR_ID . '.broker.ipc')
                    ->end()
                    ->sub('services', false)
                        ->sub('cloud_pricing', false)
                            ->node('enabled', false)
                            ->node('time', '1 0,12 * * *')
                            ->node('workers', 4)
                            ->node('daemon', false)
                            ->node('memory_limit', 0)
                            ->node('timezone', 'UTC')
                            ->node('log', '/dev/null')
                            ->node('log_level', 'WARN')
                        ->end()
                        ->sub('images_builder', false)
                            ->node('enabled', false)
                            ->node('time', '* * * * *')
                            ->node('workers', 4)
                            ->node('daemon', true)
                            ->node('memory_limit', 0)
                            ->node('timezone', null)
                            ->node('log', '/dev/null')
                            ->node('log_level', 'WARN')
                            ->node('idle', 30)
                        ->end()
                        ->sub('images_cleanup', false)
                            ->node('enabled', false)
                            ->node('time', '0/20 * * * *')
                            ->node('workers', 1)
                            ->node('daemon', false)
                            ->node('memory_limit', 0)
                            ->node('timezone', null)
                            ->node('log', '/dev/null')
                            ->node('log_level', 'WARN')
                        ->end()
                        ->sub('lease_manager', false)
                            ->node('enabled', false)
                            ->node('time', '2/20 * * * *')
                            ->node('workers', 3)
                            ->node('daemon', false)
                            ->node('memory_limit', 0)
                            ->node('timezone', null)
                            ->node('log', '/dev/null')
                            ->node('log_level', 'WARN')
                        ->end()
                        ->sub('rotate', false)
                            ->node('enabled', false)
                            ->node('time', '17 */2 * * *')
                            ->node('workers', 1)
                            ->node('daemon', false)
                            ->node('memory_limit', 0)
                            ->node('timezone', null)
                            ->node('log', '/dev/null')
                            ->node('log_level', 'WARN')
                            ->sub('delete', false)
                                ->node('limit', 1000)
                                ->node('sleep', 60)
                            ->end()
                            ->sub('keep', false)
                                ->sub('scalr', false)
                                    ->node('logentries', '-10 days')
                                    ->node('scripting_log', '-7 days')
                                    ->node('events', '-2 months')
                                    ->node('messages', '-10 days')
                                    ->node('webhook_history', '-30 days')
                                    ->node('syslog', 1000000)
                                ->end()
                                ->sub('analytics', false)
                                    ->node('poller_sessions', '-7 days')
                                    ->node('usage_h', '-14 days')
                                ->end()
                            ->end()
                        ->end()
                        ->sub('scalarizr_messaging', false)
                            ->node('enabled', false)
                            ->node('time', '* * * * *')
                            ->node('workers', 4)
                            ->node('daemon', true)
                            ->node('memory_limit', 0)
                            ->node('timezone', null)
                            ->node('log', '/dev/null')
                            ->node('log_level', 'WARN')
                            ->sub('replicate', false)
                                ->node('type', [])
                                ->node('account', [])
                            ->end()
                        ->end()
                        ->sub('scaling', false)
                            ->node('enabled', false)
                            ->node('time', '*/2 * * * *')
                            ->node('workers', 14)
                            ->node('daemon', false)
                            ->node('memory_limit', 0)
                            ->node('timezone', null)
                            ->node('log', '/dev/null')
                            ->node('log_level', 'WARN')
                        ->end()
                        ->sub('scheduler', false)
                            ->node('enabled', false)
                            ->node('time', '* * * * *')
                            ->node('workers', 4)
                            ->node('daemon', false)
                            ->node('memory_limit', 0)
                            ->node('timezone', null)
                            ->node('log', '/dev/null')
                            ->node('log_level', 'WARN')
                        ->end()
                        ->sub('server_terminate', false)
                            ->node('enabled', false)
                            ->node('time', '* * * * *')
                            ->node('workers', 4)
                            ->node('daemon', true)
                            ->node('memory_limit', 0)
                            ->node('timezone', null)
                            ->node('log', '/dev/null')
                            ->node('log_level', 'WARN')
                            ->node('idle', 30)
                        ->end()
                    ->end()
                ->end()

                ->sub('dns')
                    ->sub('global')
                        ->node('default_domain_name')
                        ->node('enabled')
                        ->node('nameservers')
                    ->end()
                    ->sub('static')
                        ->node('enabled')
                        ->node('extended', false)
                        ->node('nameservers')
                        ->node('domain_name')
                    ->end()
                    ->sub('mysql')
                        ->node('driver', 'mysqli')
                        ->node('host')
                        ->node('port', null)
                        ->node('name')
                        ->node('user')
                        ->node('pass')
                    ->end()
                ->end()

                ->node('environment', 'PROD')

                ->sub('email')
                    ->node('address')
                    ->node('name', null)
                    ->node('delimiter', 'crlf')
                ->end()

                ->sub('endpoint')
                    ->node('scheme', 'http')
                    ->node('host')
                ->end()

                ->sub('hosted', false)
                    ->node('enabled', false)
                    ->sub('analytics', false)
                        ->node('managed_accounts', [])
                    ->end()
                ->end()

                ->node('instances_connection_policy')

                ->sub('load_statistics', false)
                    ->sub('connections')
                        ->sub('plotter')
                            ->node('scheme', 'http')
                            ->node('host', null)
                            ->node('port', 8080)
                        ->end()
                    ->end()
                ->end()

                ->sub('openstack', false)
                    ->node('user_data_method', 'both')
                ->end()

                ->sub('nebula', false)
                    ->node('user_data_method', 'meta-data')
                ->end()

                ->sub('phpunit', false)
                    ->node('skip_functional_tests', true)
                    ->node('userid')
                    ->node('envid')
                    ->sub('openstack', false)
                        ->node('platforms', [])
                    ->end()
                    ->sub('cloudstack', false)
                        ->node('platforms', [])
                    ->end()
                ->end()

                ->node('rss_cache_lifetime', 300)

                ->sub('scalarizr_update')
                    ->node('mode', 'solo')
                    ->node('server_url', 'http://update.scalr.net/')
                    ->node('api_port', '')
                    ->node('default_repo', '')
                    ->node('devel_repos', false)
                    ->node('repos')
                ->end()

                ->sub('script', false)
                    ->sub('timeout', false)
                        ->node('sync', 180)
                        ->node('async', 1200)
                    ->end()
                ->end()

                ->sub('system', false)
                    ->node('instances_connection_timeout', 4)
                    ->node('default_disable_firewall_management', false)
                    ->node('server_terminate_timeout', '+3 minutes')
                    ->sub('scripting', false)
                        ->node('logs_storage', 'instance')
                        ->node('default_instance_log_rotation_period', 3600)
                        ->node('default_abort_init_on_script_fail', false)
                    ->end()
                    ->sub('global_variables', false)
                        ->node('format', array())
                    ->end()
                ->end()

                ->sub('ui', false)
                    ->node('support_url', 'https://groups.google.com/d/forum/scalr-discuss')
                    ->node('wiki_url', 'http://wiki.scalr.com')
                    ->sub('recaptcha', false)
                        ->node('public_key', '')
                        ->node('private_key', '')
                    ->end()
                    ->node('mindterm_enabled', false)
                    ->node('server_display_convention', 'auto')
                    // Hidden stuff, should not be in config.yml
                    ->sub('pma', false)
                        ->node('key', '')
                        ->node('url', '')
                        ->node('server_ip', '')
                    ->end()
                    ->node('tender_api_key', '')
                    ->node('tender_site_key', '')
                    ->node('announcements_rss_url', 'http://www.scalr.com/blog/rss.xml')
                    ->node('changelog_rss_url', 'https://scalr-wiki.atlassian.net/wiki/createrssfeed.action?types=blogpost&spaces=docs&sort=created&maxResults=100&showContent=true&timeSpan=100')
                ->end()
            ->end()
        ;

        return $this;
    }

    /**
     * Defines subset
     *
     * @param   string    $name     Parameter bag name
     * @param   bool      $required optional Whether this bag is required.
     * @return  Extension
     */
    public function sub($name, $required = true)
    {
        return new ClosureInvoker(function ($method, $invoker) use ($name, $required) {
            $arguments = array_slice(func_get_args(), 2);
            $arguments[0] = $name . '.' . $arguments[0];

            if ($method != 'sub' && strpos($arguments[0], '.')) {
                $p = preg_replace('/\.[^\.]+$/', '', $arguments[0]);
                if ($required) {
                    $obj = new \stdClass();
                    $invoker->getObject()->setParameter($p, $obj);
                }
                $invoker->getObject()->paths[$p] = true;
            }

            $ret = call_user_func_array(array($invoker->getObject(), $method), $arguments);
            if ($ret instanceof ClosureInvoker) {
                $ret->parent = $invoker;
                return $ret;
            } else {
                return $invoker;
            }
        }, $this);
    }

    /**
     * Appends new scalar or scalarArray node to Extension
     *
     * @param   string     $name          Dot notaion name.
     * @param   mixed      $defaultValue  optional Default value for the parameter.
     * @return  Extension
     * @throws  Exception\ExtensionException
     */
    public function node($name, $defaultValue = null)
    {
        if ($name === null || $name == '') {
            throw new Exception\ExtensionException(sprintf(
                'Node name must not be empty.'
            ));
        }

        $obj = new \stdClass();

        if (func_num_args() > 1) {
            if ($defaultValue !== null && !is_scalar($defaultValue)) {
                $valid = true;
                if (!is_array($defaultValue)) {
                    $valid = false;
                } else {
                    //Additional check that all values of arrays are scalar.
                    foreach ($defaultValue as $k => $v) {
                        if ($v !== null && !is_scalar($v)) {
                            $valid = false;
                            break;
                        }
                    }
                }
                if (!$valid) {
                    throw new Exception\ExtensionException(sprintf(
                        'Default node value must be scalar or scalarArray (one dimension array with numeric keys), "%s" given.',
                        gettype($defaultValue)
                    ));
                }
            }
            $obj->default = $defaultValue;
        }

        $this->parameters[$name] = $obj;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @see IteratorAggregate::getIterator()
     */
    public function getIterator()
    {
        $obj = new \ArrayObject($this->parameters);
        return $obj->getIterator();
    }

    /**
     * Checks whether scalar node is defined
     *
     * @param   string   $name  Dot notation key
     * @return  boolean  Returns true if config node is defined
     */
    public function defined($name)
    {
        return array_key_exists($name, $this->parameters);
    }

    public function __invoke($parameter)
    {
        return isset($this->parameters[$parameter]) ? $this->parameters[$parameter] : null;
    }

    /**
     * Sets parameter with specified name
     *
     * @param   string     $name
     * @param   object     $value
     */
    public function setParameter($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    /**
     * Gets parameter with dot notation key
     *
     * @param   string      $name  Dot notaion key
     * @return  object
     */
    public function getParameter($name)
    {
        return isset($this->parameters[$name]) ? $this->parameters[$name] : null;
    }
}
