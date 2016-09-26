<?php

namespace Scalr\Net\Ldap;

use Scalr\Net\Ldap\Exception\LdapException;

/**
 * LdapClient
 *
 * @author  Vitaliy Demidov   <vitaliy@scalr.com>
 * @since   06.06.2013
 */
class LdapClient
{

    const VERSION = '0.5';

    /**
     * When regular binding is used, a user can be authenticated by User logon name (user@domain.com).
     */
    const BIND_TYPE_REGULAR = 'regular';

    /**
     * When simple binding is used, a user can be authenticated by Full name, Display name or sAMAccountName
     */
    const BIND_TYPE_SIMPLE = 'simple';

    /**
     * When openldap binding is used, a user can be authenticated by full dn
     */
    const BIND_TYPE_OPENLDAP = 'openldap';

    /**
     * Ldap config object
     *
     * @var LdapConfig
     */
    private $config;

    /**
     * User's rdn
     *
     * @var string
     */
    private $username;

    /**
     * User's email which is retrieved from LDAP
     *
     * @var string
     */
    private $email;

    /**
     * User's fullname which is retrieved from LDAP
     *
     * @var string
     */
    private $fullname;

    /**
     * User's password
     *
     * @var string
     */
    private $password;

    /**
     * User's uid. Used for openldap support
     *
     * @var string
     */
    private $uid;

    /**
     * Ldap connection link identifier
     *
     * @var resource
     */
    private $conn;

    /**
     * Cached Distinguished Name of the user
     *
     * @var string
     */
    private $dn;

    /**
     * Cached memberof distinguished names
     *
     * @var array
     */
    private $memberofDn;

    /**
     * Whether ldap bound
     *
     * @var bool
     */
    private $isbound;

    /**
     * Log output
     *
     * @var array
     */
    private $aLog;

    /**
     * Constructor
     *
     * @param  LdapConfig  $config    LDAP config
     * @param  string      $username  LDAP rdn to check. It should look like login@host.domain
     * @param  string      $password  LDAP password for the specified user
     * @throws Exception\LdapException
     */
    public function __construct(LdapConfig $config, $username, $password, $uid=null)
    {
        $this->config = $config;
        $this->username = $username;
        $this->password = $password;
        $this->uid = $uid;
        $this->dn = null;
        $this->isbound = false;
        $this->aLog = array();
        $this->log('LdapClient v-%s', self::VERSION);
    }

    /**
     * Adds formatted string to log output
     *
     * @param   string     $format format string. see sprintf rules
     * @param   mixed      $args   optional argument 1
     * @param   mixed      $_      optional argument list
     */
    protected function log($format, $args = null, $_ = null)
    {
        if (!empty($this->config->debug)) {
            $string = call_user_func_array('sprintf', func_get_args());
            $this->aLog[] = sprintf("%s - %s\n", date('i:s'), $string);
        }
    }

    /**
     * Gets log
     *
     * @return  string  Returns the log output
     */
    public function getLog()
    {
        return join('', $this->aLog);
    }

    /**
     * Clears log
     */
    public function clearLog()
    {
        $this->aLog = array();
    }

    /**
     * Gets LdapConfig
     *
     * @return  LdapConfig Returns LdapConfig instance
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Gets username
     *
     * @return string Returns username which should look like login@host.domain
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Gets LDAP connection link identifier
     *
     * @return  resource Returns LDAP connection link edentifier
     * @throws  Exception\LdapException
     */
    protected function getConnection()
    {
        if (!$this->conn) {
            $this->conn = ldap_connect($this->config->host, $this->config->port);

            $this->log('LDAP Server is:%s port:%s - %s', $this->config->host,
                ($this->config->port ? $this->config->port : 'default'),
                ($this->conn ? 'OK' : 'Failed'));

            if ($this->conn == false) {
                throw new Exception\LdapException(sprintf(
                    "Could not establish LDAP connection to host '%s' port '%d'",
                    $this->config->host, $this->config->port
                ));
            }
            //Sets protocol version
            ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, 3);
            // We need this for doing an LDAP search.
            ldap_set_option($this->conn, LDAP_OPT_REFERRALS, 0);
        }

        return $this->conn;
    }

    /**
     * Gets last LDAP error
     *
     * @return  string
     */
    public function getLdapError()
    {
        return isset($this->conn) ? ldap_error($this->conn) : '';
    }

    /**
     * Binds to the LDAP directory with specified RDN and password.
     *
     * @param   string     $username  RDN
     * @param   string     $password  Password
     * @return  bool       Returns TRUE on success or FALSE otherwise
     */
    protected function bindRdn($username = null, $password = null)
    {
        if (!func_num_args()) {
            if ($this->config->user !== null) {
                //Admin user is provided in config.
                $username = $this->config->user;
                $password = $this->config->password;
                if ($this->config->bindType == \Scalr\Net\Ldap\LdapClient::BIND_TYPE_OPENLDAP) {
                    $username = "{$this->config->usernameAttribute}={$username},{$this->config->baseDn}";
                }
            } else {
                //Without admin user we use specified rdn
                $username = $this->username;
                $password = $this->password;
            }
        }

        $res = @ldap_bind($this->conn, (string)$username, (string)$password);

        $this->isbound = $res ? true : false;

        $this->log("Bind username:%s password:%s - %s",
            $username, str_repeat('*', strlen($password)),
            ($this->isbound ? 'OK' : 'Failed')
        );

        return $res;
    }

    /**
     * Unbinds LDAP connection
     *
     * @return bool
     */
    public function unbind()
    {
        return $this->conn ? @ldap_unbind($this->conn) : true;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->unbind();
    }

    /**
     * Gets the user email
     *
     * @return  string  Returns user email address
     */
    public function getEmail()
    {
        if ($this->email)
            return $this->email;
        else {
            if ($this->uid)
                return $this->uid;
            else
                return $this->username;
        }
    }

    /**
     * Gets the user's full name
     *
     * @return  string  Returns user's full name
     */
    public function getFullName()
    {
        return $this->fullname;
    }

    /**
     * Checks if the username exists in the LDAP
     *
     * @return  bool Returns true on success or false if user does not exist
     * @throws  LdapException
     */
    public function isValidUsername()
    {
        $this->log('%s is called.', __FUNCTION__);

        if (empty($this->config->user) || !isset($this->config->password)) {
            throw new LdapException(
                "Both LDAP user and password must be provided in the config "
              . "for the scalr.connections.ldap parameter's bag."
            );
        }

        $this->getConnection();

        if (($ret = $this->bindRdn()) == false) {
            throw new LdapException(sprintf(
                "Cannot bind to ldap server with username '{$this->config->user}' and password in the scalr.connections.ldap section of config. %s"
                , $this->getLdapError()));
        } else {

            if (stristr($this->username, "{$this->getConfig()->usernameAttribute}="))
                $filter = sprintf('(&%s(' . $this->getConfig()->usernameAttribute . '=%s))', $this->config->userFilter, self::realEscape($this->uid));
            else
                $filter = sprintf('(&%s(' . $this->getConfig()->usernameAttribute . '=%s))', $this->config->userFilter, self::realEscape(strtok($this->username, '@')));

            $attrs = array('dn', 'memberof');
            if ($this->config->mailAttribute) {
                $mailAttribute = strtolower($this->config->mailAttribute);
                $attrs[] = $mailAttribute;
            }

            $query = @ldap_search(
                $this->conn, $this->config->baseDn, $filter, $attrs, 0, 1
            );

            $this->log("Query baseDn (3):%s filter:%s, attributes: %s - %s",
                $this->config->baseDn, $filter, join(', ', $attrs),
                ($query !== false ? 'OK' : 'Failed')
            );

            if ($query !== false) {
                $results = ldap_get_entries($this->conn, $query);
                if ($results['count'] == 1) {
                    //Caches base DN to increase performance
                    $this->dn = $results[0]['dn'];
                    $this->memberofDn = $results[0]['memberof'];
                    if (isset($mailAttribute) && isset($results[0][$mailAttribute])) {
                        $this->email = (is_array($results[0][$mailAttribute]) ? $results[0][$mailAttribute][0] : $results[0][$mailAttribute]) . '';
                        $this->log('Email has been retrieved: %s', $this->email);
                    }
                    if (isset($this->memberofDn['count'])) {
                        unset($this->memberofDn['count']);
                    }
                    $ret = true;
                } else {
                    $ret = false;
                }
            } else {
                $ret = false;
            }
        }

        return $ret;
    }

    /**
     * Checks is this user can be authenticated to LDAP
     *
     * @return  boolean    Returns true on success or false otherwise
     */
    public function isValidUser()
    {
        $this->log('%s is called.', __FUNCTION__);

        if (empty($this->username) || !isset($this->password)) {
            return false;
        }

        $this->getConnection();

        $ret = $this->bindRdn($this->username, $this->password);

        //It is not enough only successfull bind.
        //It should find the user with the specified credentials.
        if ($ret === false) {
            $this->log(sprintf(
                "Could not bind LDAP. %s",
                $this->getLdapError()
            ));
        } else {
            $attrs = array('dn', 'memberof');
            if ($this->config->mailAttribute) {
                $mailAttribute = strtolower($this->config->mailAttribute);
                $attrs[] = $mailAttribute;
            }

            if ($this->config->fullNameAttribute) {
                $fullNameAttribute = strtolower($this->config->fullNameAttribute);
                $attrs[] = $fullNameAttribute;
            }

            if (preg_match('/(^|,)cn=/i', $this->username) || ($this->config->usernameAttribute && preg_match('/'.$this->config->usernameAttribute.'=/i', $this->username))) {
                //username is provided as distinguished name.
                //We need to make additional query to validate user's password
                $filter = sprintf('(&%s(' . $this->config->usernameAttribute . '=*))', $this->config->userFilter);
                $query = @ldap_search($this->conn, $this->username, $filter, $attrs, 0, 1);
                $this->log("Query baseDn (2):%s filter:%s, attributes: %s - %s",
                    $this->username, $filter, join(', ', $attrs),
                    ($query !== false ? 'OK' : 'Failed')
                );
            } else {
                $filter = sprintf('(&%s(' . $this->config->usernameAttribute . '=%s))', $this->config->userFilter, self::realEscape(strtok($this->username, '@')));
                $query = @ldap_search($this->conn, $this->config->baseDn, $filter, $attrs, 0, 1);
                $this->log("Query baseDn (1):%s filter:%s, attributes: %s - %s",
                    $this->config->baseDn, $filter, join(', ', $attrs),
                    ($query !== false ? 'OK' : 'Failed')
                );
            }


            if ($query !== false) {
                $results = ldap_get_entries($this->conn, $query);

                $this->log(sprintf("Query result count: %s", $results['count']));

                if ($results['count'] == 1) {
                    //If it is successful, we should take the DN and bind
                    //again using that DN and the provided password.
                    $this->dn = $results[0]['dn'];
                    $this->memberofDn = $results[0]['memberof'];
                    if (isset($mailAttribute) && isset($results[0][$mailAttribute])) {
                        $this->email = (is_array($results[0][$mailAttribute]) ? $results[0][$mailAttribute][0] : $results[0][$mailAttribute]) . '';
                        $this->log('Email has been retrieved: %s', $this->email);
                    }
                    if (isset($fullNameAttribute) && isset($results[0][$fullNameAttribute])) {
                        $this->fullname = (is_array($results[0][$fullNameAttribute]) ? $results[0][$fullNameAttribute][0] : $results[0][$fullNameAttribute]) . '';
                        $this->log('Full name has been retrieved: %s', $this->fullname);
                    }

                    $this->log(sprintf("Query result memberofDn: %s", count($this->memberofDn['count'])));

                    if (isset($this->memberofDn['count'])) {
                        unset($this->memberofDn['count']);
                    }

                    $this->log(sprintf("Query result DN: %s", $this->dn));

                    //Now this should either succeed or fail properly
                    $ret = $this->bindRdn(self::escape($this->dn), $this->password);
                } else {
                    $ret = false;
                }
            } else {
                $ret = false;
            }
        }

        return $ret;
    }

    /**
     * Retrieves information (for now only displayname/description) about groups from LDAP
     *
     * @param  array $groups List of group ids
     * @return array Returns array of groups with their description
     * @throws Exception\LdapException
     */
    public function getGroupsDetails($groups)
    {
        $result = array();

        $this->log('%s is called.', __FUNCTION__);

        if (empty($this->config->user) || !isset($this->config->password)) {
            throw new Exception\LdapException(
                "Both LDAP user and password must be provided in the config "
              . "for the scalr.connections.ldap parameter's bag."
            );
        }

        $this->getConnection();

        $ret = $this->bindRdn();

        //Ldap bind
        if ($ret === false) {
            throw new Exception\LdapException(sprintf(
                "Could not bind LDAP. %s",
                $this->getLdapError()
            ));
        }

        if (empty($groups)) {
            return $result;
        }

        $baseDn = !empty($this->config->baseDnGroups) ? $this->config->baseDnGroups : $this->config->baseDn;

        $filter = "(&" . $this->config->groupFilter .  "(|";
        foreach ($groups as $group) {
            $filter .= "(" . $this->getConfig()->groupnameAttribute . "=" . self::realEscape($group) . ")";
        }
        $filter .= "))";

        $search = @ldap_search(
            $this->conn, $baseDn, $filter, array($this->getConfig()->groupnameAttribute, $this->getConfig()->groupDisplayNameAttribute)
        );

        $this->log("Query group details baseDn:%s filter:%s - %s",
            $baseDn, $filter,
            ($search !== false ? 'OK' : 'Failed ('. ldap_error($this->conn) . ')')
        );

        if ($search === false) {
            throw new Exception\LdapException(sprintf(
                "Could not perform ldap_search. %s",
                $this->getLdapError()
            ));
        }

        $results = ldap_get_entries($this->conn, $search);

        for ($item = 0; $item < $results['count']; $item++) {
            $id = $results[$item][strtolower($this->getConfig()->groupnameAttribute)][0];
            $name = $results[$item][strtolower($this->getConfig()->groupDisplayNameAttribute)][0];
            $result[$id] = $name;
        }

        return $result;
    }

    /**
     * Gets the list of the groups in which specified user has memberships.
     *
     * @return  array     Returns array of the sAMAccount name of the Groups
     * @throws  Exception\LdapException
     */
    public function getUserGroups()
    {
        $this->log('%s is called.', __FUNCTION__);

        $name = strtok($this->username, '@');

        $groups = array();

        $this->getConnection();

        //Ldap bind
        if (!$this->isbound && (!empty($this->config->user) && !empty($this->password))) {
            if ($this->bindRdn() === false) {
                throw new Exception\LdapException(sprintf(
                    "Could not bind LDAP. %s",
                    $this->getLdapError()
                ));
            }
        }

        if (empty($this->dn)) {
            $filter = sprintf('(&%s(' . $this->getConfig()->usernameAttribute . '=%s))', $this->config->userFilter, self::realEscape($name));
            $query = @ldap_search(
                $this->conn, $this->config->baseDn, $filter, array('dn'), 0, 1
            );

            $this->log("Query user baseDn:%s filter:%s - %s",
                $this->config->baseDn, $filter,
                ($query !== false ? 'OK' : 'Failed')
            );

            if ($query === false) {
                throw new Exception\LdapException(sprintf(
                    "Could not perform ldap_search. %s",
                    $this->getLdapError()
                ));
            }

            $results = ldap_get_entries($this->conn, $query);

            $this->dn = $results[0]['dn'];
        }

        $baseDn = !empty($this->config->baseDnGroups) ? $this->config->baseDnGroups : $this->config->baseDn;

        if ($this->memberofDn !== null && empty($this->memberofDn)) {
            //User has no membership in any group.
            return array();
        }

        if ($this->getConfig()->bindType == 'openldap') {
            $uid = ($this->uid) ? $this->uid : $this->username;

            if ($this->getConfig()->groupMemberAttributeType == 'unix_netgroup') {
                $filter = "(&" . $this->config->groupFilter . "(" . $this->getConfig()->groupMemberAttribute . ""
                    . ($this->config->groupNesting ? ":1.2.840.113556.1.4.1941:" : "")
                    . '=\(,' . self::escape($uid) . ',\)))';
            } elseif ($this->getConfig()->groupMemberAttributeType == 'regular') {
                $filter = "(&" . $this->config->groupFilter . "(" . $this->getConfig()->groupMemberAttribute . ""
                    . ($this->config->groupNesting ? ":1.2.840.113556.1.4.1941:" : "")
                    . '=' . self::escape($uid) . '))';
            } elseif ($this->getConfig()->groupMemberAttributeType == 'user_dn') {
                $filter = "(&" . $this->config->groupFilter . "(" . $this->getConfig()->groupMemberAttribute . ""
                    . ($this->config->groupNesting ? ":1.2.840.113556.1.4.1941:" : "")
                    . '=' . self::escape($this->username) . '))';
            }
        } else {
            $filter = "(&" . $this->config->groupFilter . "(" . $this->getConfig()->groupMemberAttribute . ""
                . ($this->config->groupNesting ? ":1.2.840.113556.1.4.1941:" : "")
                . "=" . ldap_escape($this->dn, null, LDAP_ESCAPE_FILTER) . "))";
        }

        $search = @ldap_search(
            $this->conn, $baseDn, $filter, array($this->getConfig()->groupnameAttribute)
        );

        $this->log("Query user's groups baseDn:%s filter:%s - %s",
            $baseDn, $filter,
            ($search !== false ? 'OK' : 'Failed')
        );

        if ($search === false) {
            throw new Exception\LdapException(sprintf(
                "Could not perform ldap_search. %s",
                $this->getLdapError()
            ));
        }

        $results = ldap_get_entries($this->conn, $search);

        for ($item = 0; $item < $results['count']; $item++) {
            $groups[] = $results[$item][strtolower($this->getConfig()->groupnameAttribute)][0];
        }

        $this->log("Found groups: %s", implode(", ", $groups));

        return $groups;
    }

    public function __sleep()
    {
        return array('config');
    }

    public function __wakeup()
    {
        $this->getConnection();
    }

    /**
     * Escapes query string
     *
     * @param   string   $string The query string
     * @return  string
     */
    public static function escape($string)
    {
        return preg_replace(
            array(
                '/[\r\n]+/',
                '/\\\\/',
                '/\*/',
                '/\(/',
                '/\)/',
                '/\0/',
                '/\//',
                '/á/',
                '/é/',
                '/í/',
                '/ó/',
                '/ú/',
                '/ñ/',
            ),
            array(
                '',
                '\\\\5C',
                '\\\\2A',
                '\\\\28',
                '\\\\29',
                '\\\\00',
                '\\\\2F',
                '\\\\E1',
                '\\\\E9',
                '\\\\ED',
                '\\\\F3',
                '\\\\FA',
                '\\\\F1',
            ),
            $string
        );
    }

    /**
     * Escapes query string including asterisk and parentheses
     *
     * @param   string   $string The query string
     * @return  string
     */
    public static function realEscape($string)
    {
        return preg_replace(
            array(
                '/[\r\n]+/',
                '/(^ |[\\\\]|[,#+<>;"=*\(\)\/]| $)/',
            ),
            array(
                '',
                '\\\\$1',
            ),
            $string
        );
    }
}