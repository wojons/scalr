<?php

namespace Scalr\Net\Ldap;

/**
 * LdapConfig
 *
 * @author  Vitaliy Demidov   <vitaliy@scalr.com>
 * @since   06.06.2013
 *
 * @property  string $host          A ldap host
 * @property  int    $port          A ldap port
 * @property  string $user          Username
 * @property  string $password      Password
 * @property  string $baseDn        Base distinguished name
 * @property  string $baseDnGroups  Base DN for groups filtering
 * @property  bool   $debug         Debug mode
 * @property  bool   $groupNesting  Should we add support for nested groups or not.
 * @property  string $domain        The domain which will be used when user logs in system omitting it.
 * @property  string $userFilter    The users filter
 * @property  string $groupFilter   The groups filter
 * @property  string $bindType      LDAP bind type
 * @property  string $mailAttribute The name of the attribute which contains email address of the user in the LDAP
 */
class LdapConfig
{
    /**
     * LDAP connection host
     *
     * @var string
     */
    private $host;

    /**
     * LDAP connection port
     *
     * @var int
     */
    private $port;

    /**
     * Username
     *
     * @var string
     */
    private $user;

    /**
     * Password
     *
     * @var string
     */
    private $password;

    /**
     * Base DN
     *
     * @var string
     */
    private $baseDn;

    /**
     * Gase DN for Groups
     *
     * @var string
     */
    private $baseDnGroups;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var bool
     */
    private $groupNesting;

    /**
     * Base domain which will be used when user logs in system omitting @domain suffix.
     *
     * @var string
     */
    private $domain;

    /**
     * Users filter
     *
     * @var string
     */
    private $userFilter;

    /**
     * Groups filter
     *
     * @var string
     */
    private $groupFilter;

    /**
     * LDAP bind type
     *
     * @var string
     */
    private $bindType;

    /**
     * The name of the attribute which contains
     * email address of the user in the LDAP
     *
     * @var string
     */
    private $mailAttribute;

    /**
     * Contstructor
     *
     * @param   string     $host           A connection host
     * @param   int        $port           A connection port
     * @param   string     $user           The username
     * @param   string     $password       The user's password
     * @param   string     $baseDn         The base DN
     * @param   string     $userFilter     The users filter
     * @param   string     $groupFilter    The groups filter
     * @param   string     $domain         optional The domain which is used when user logs in system omitting it
     * @param   string     $baseDnGroups   optional The base DN for Gropus
     * @param   bool       $groupNesting   optional Nested groups support is enabled by default.
     * @param   string     $bindType       optional LDAP bind type
     * @param   string     $mailAttribute  optional The name of the attribute which contains email address of the user in the LDAP
     * @param   bool       $debug          optional Turns on debug mode
     */
    public function __construct($host, $port, $user, $password, $baseDn, $userFilter, $groupFilter, $domain = null,
                                $baseDnGroups = null, $groupNesting = null, $bindType = null, $mailAttribute = null, $debug = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
        $this->baseDn = $baseDn;
        $this->userFilter = $userFilter;
        $this->groupFilter = $groupFilter;
        $this->domain = empty($domain) ? null : $domain;
        $this->baseDnGroups = $baseDnGroups;
        $this->groupNesting = $groupNesting === null ? true : (bool) $groupNesting;
        $this->bindType = ($bindType == LdapClient::BIND_TYPE_SIMPLE ? LdapClient::BIND_TYPE_SIMPLE : LdapClient::BIND_TYPE_REGULAR);
        $this->mailAttribute = (empty($mailAttribute) ? null : (string)$mailAttribute);
        $this->debug = $debug === null ? false : (bool) $debug;
    }

    public function __set($name, $value)
    {
        if (!property_exists($this, $name)) {
            throw new Exception\LdapException(sprintf(
                "Property '%s' does not exist for '%s' class.",
                $name, get_class($this)
            ));
        }
        $this->$name = $value;
    }

    /**
     * Gets default domain
     *
     * @return  string Returns default domain
     */
    public function getDomain()
    {
        $domain = '';
        if (!empty($this->domain)) {
            $domain = $this->domain;
        } else if (preg_match_all('/DC=([^,]+)/i', $this->baseDn, $m)) {
            foreach ($m[1] as $d) {
                $domain .= '.' . $d;
            }
            $domain = ltrim($domain, ". ");
        }
        return $domain;
    }

    public function __get($name)
    {
        return isset($this->$name) ? $this->$name : null;
    }

    public function __isset($name)
    {
        return isset($this->$name);
    }

    public function __invoke($name)
    {
        return isset($this->$name) ? $this->$name : null;
    }
}