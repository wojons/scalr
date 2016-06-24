<?php

use Scalr\Util\CryptoTool;
use Scalr\Model\Entity\Account\User;

/**
 * Scalr Session class
 *
 * @method  string          getCloudynToken() getCloudynToken()       Gets a Cloudyn Token from session
 * @method  Scalr_Session   setCloudynToken() setCloudynToken($token) Sets a Cloudyn Token into session
 * @method  string          getToken()        getToken()              Gets special token for security
 * @method  Scalr_Session   setToken()        setToken($token)        Sets special token for security
 * @method  array           getDebugMode()    getDebugMode()
 * @method  Scalr_Session   setDebugMode()    setDebugMode($enabled)
 *
 */
class Scalr_Session
{
    /**
     * Effective user identifier
     *
     * @var int
     */
    private $userId;

    /**
     * Real user id which is used to sign in to Scalr
     *
     * If real user is the same as effective user this variable will be null
     *
     * @var int|null
     */
    private $ruid;

    private $initTime;

    private $lastTime;

    private $sault;

    private $hash;

    private $token;

    private $hashpwd;

    private $cloudynToken;

    private $restored = false;

    private static $_session = null;

    /**
     * @var ReflectionClass
     */
    private static $refClass;

    /**
     * Effective user identifier (euid)
     */
    const SESSION_USER_ID = 'userId';

    const SESSION_INIT_TIME = 'initTime';

    const SESSION_LAST_TIME = 'lastTime';

    const SESSION_HASH    = 'hash';

    const SESSION_SAULT   = 'sault';

    /**
     * Real user identifier (ruid) which is used to sign in to Scalr
     */
    const SESSION_RUID = 'ruid';

    const SESSION_CLOUDYN_TOKEN = 'cloudynToken';

    const SESSION_TOKEN = 'token'; // against CSRF

    const SESSION_DEBUG_MODE = 'debugMode'; // internal debug property

    /**
     *
     * @param   bool    $isAutomaticRequest
     * @return  Scalr_Session
     */
    public static function getInstance($isAutomaticRequest = false)
    {
        if (self::$_session === null) {
            self::$_session = new Scalr_Session();
            self::$_session->hashpwd = CryptoTool::hash(@file_get_contents(APPPATH."/etc/.cryptokey"));
            ini_set('session.cookie_httponly', true);

            if (!filter_has_var(INPUT_COOKIE, session_name()) || !preg_match('/^[-,a-z\d]{1,128}$/i', filter_input(INPUT_COOKIE, session_name()))) {
                self::sessionLog('session is not valid, regenerate:' . __LINE__);
                session_id(uniqid());
                static::startSession();
                session_regenerate_id();
                session_write_close();
            }
        }

        if (!self::$_session->restored) {
            self::$_session->restored = true;
            self::restore(true, $isAutomaticRequest);

            $token = self::$_session->getToken();
            if (empty($token)) {
                if ($cookieToken = filter_input(INPUT_COOKIE, self::SESSION_TOKEN)) {
                    $hash = self::getInstance()->hashpwd;
                    // validate token value
                    if ($signature = filter_input(INPUT_COOKIE, 'scalr_signature')) {
                        if (CryptoTool::hash("{$signature}:{$hash}") === $cookieToken) {
                            self::$_session->setToken($cookieToken);
                        }
                    } else {
                        $id = session_id();
                        self::sessionLog("session_id():" . __LINE__);
                        if (CryptoTool::hash("{$id}:{$hash}") === $cookieToken) {
                            self::$_session->setToken($cookieToken);
                        }
                    }
                }
            }
        }

        return self::$_session;
    }

    /**
     * Starts session suppressing warnings and notices
     */
    private static function startSession()
    {
        //Avoids annoying: E_WARNING session_start(): Memcached: Failed to read session data: NOT FOUND
        $errorLevel = error_reporting(E_ERROR);
        session_start();
        //Restores original error reporting level
        error_reporting($errorLevel);
    }

    /**
     * Creates a session
     *
     * @param   int  $userId  The effective identifier of the user
     * @param   int  $ruid    optional Real user identifier which is used to sign in to Scalr (Admin UserId)
     */
    public static function create($userId, $ruid = null)
    {
        static::startSession();
        session_regenerate_id(true);

        self::sessionLog("session_start():" . __LINE__);

        $_SESSION[__CLASS__][self::SESSION_USER_ID] = $userId;
        $_SESSION[__CLASS__][self::SESSION_RUID] = $ruid;
        $_SESSION[__CLASS__][self::SESSION_INIT_TIME] = time();
        $_SESSION[__CLASS__][self::SESSION_LAST_TIME] = time();

        $sault = CryptoTool::sault();

        $_SESSION[__CLASS__][self::SESSION_SAULT] = $sault;
        $_SESSION[__CLASS__][self::SESSION_HASH] = self::createHash($userId, $sault);

        if (!$ruid) {
            $id = session_id();

            self::sessionLog("session_id():" . __LINE__);

            $hash = self::getInstance()->hashpwd;

            $token = CryptoTool::hash("{$id}:{$hash}");

            $https = filter_has_var(INPUT_SERVER, 'HTTPS');

            $_SESSION[__CLASS__][self::SESSION_TOKEN] = $token;

            setcookie('scalr_token', $token, null, '/', null, $https, false);

            session_write_close();

            self::sessionLog("session_write_close():" . __LINE__);
        }

        self::restore(false);
    }

    /**
     * Return user's hash (id:email:password)
     *
     * @param   int     $userId
     * @return  string
     */
    protected static function getUserHash($userId)
    {
        $db = \Scalr::getDb();
        return $db->GetOne('SELECT CONCAT_WS(":", `id`, `email`, `password`) FROM `account_users` WHERE id = ? LIMIT 1', [$userId]);
    }

    /**
     * Return account's hash. It's used for reseting keepSession on a whole account
     *
     * @param   int     $userId
     * @return  string
     */
    protected static function getAccountHash($userId)
    {
        $db = \Scalr::getDb();
        $hash = $db->GetOne("
            SELECT `value`
            FROM client_settings
            JOIN account_users ON account_users.account_id = client_settings.clientid
            WHERE `key` = ? AND account_users.id = ?
            LIMIT 1
        ", array(Scalr_Account::SETTING_AUTH_HASH, $userId));

        if (!$hash) {
            $accountId = $db->GetOne('SELECT account_id FROM account_users WHERE id = ? LIMIT 1', array($userId));
            if ($accountId) {
                $hash = CryptoTool::sault();
                $acc = new Scalr_Account();
                $acc->loadById($accountId);
                $acc->setSetting(Scalr_Account::SETTING_AUTH_HASH, $hash);
            }
        }

        return $hash;
    }

    /**
     * @param   $userId
     * @param   $sault
     * @return  string
     */
    protected static function createHash($userId, $sault)
    {
        $hash = self::getUserHash($userId);
        return CryptoTool::hash("{$hash}:" . self::getInstance()->hashpwd . ":{$sault}");
    }

    /**
     * @param   int     $userId     ID of user
     * @param   int     $expire     Timestamp when cookie will be expired
     * @param   string  $sault
     * @param   string  $hash
     * @return  string
     */
    protected static function createCookieHash($userId, $expire, $sault, $hash)
    {
        $userHash = self::getUserHash($userId);
        $accountHash = self::getAccountHash($userId);
        return CryptoTool::hash("{$sault}:{$expire}:{$hash}:{$userHash}:{$accountHash}:" . self::getInstance()->hashpwd);
    }

    /**
     * Check if session is valid and is not expired. If no valid session, check cookie keepSession.
     *
     * @param bool $checkKeepSessionCookie  If true check cookie keepSession
     * @param bool $isAutomaticRequest      If true don't update sessionLastTime
     */
    protected static function restore($checkKeepSessionCookie = true, $isAutomaticRequest = false)
    {
        $session = self::getInstance();

        if (session_status() != PHP_SESSION_ACTIVE) {
            static::startSession();
            self::sessionLog("session_start():" . __LINE__);
        }

        $refClass = self::getReflectionClass();
        foreach ($refClass->getConstants() as $constname => $constvalue) {
            if (substr($constname, 0, 8) !== 'SESSION_') {
                continue;
            }
            $session->{$constvalue} = isset($_SESSION[__CLASS__][$constvalue]) ?
                $_SESSION[__CLASS__][$constvalue] : null;
        }

        $newhash = self::createHash($session->userId, $session->sault);
        if (! ($newhash == $session->hash && !empty($session->hash))) {
            // reset session (invalid)
            self::sessionLog("invalid hash:" . __LINE__);
            $session->userId = 0;
            $session->hash = '';

            if ($checkKeepSessionCookie && self::isCookieKeepSession()) {
                self::restore(false, $isAutomaticRequest);

                if ($session->userId) {
                    // we've recovered session, update last login
                    $u = new User();
                    Scalr::getDb()->Execute("UPDATE {$u->table()} SET {$u->columnLastLogin} = NOW() WHERE {$u->columnId} = ?", [$session->userId]);
                }
            }
        } else {
            if (strtotime(Scalr::config('scalr.security.user.session.timeout'), $session->lastTime) < time()) {
                self::sessionLog("session timeout was expired:" . __LINE__);

                if ($checkKeepSessionCookie) {
                    $_SESSION[__CLASS__][self::SESSION_USER_ID] = 0;
                    $_SESSION[__CLASS__][self::SESSION_HASH] = '';

                    self::restore($checkKeepSessionCookie, $isAutomaticRequest);
                } else {
                    $session->userId = 0;
                    $session->hash = '';
                }
                return;
            }

            if (!$isAutomaticRequest) {
                $_SESSION[__CLASS__][self::SESSION_LAST_TIME] = $session->lastTime = time();
            }

            if (strtotime(Scalr::config('scalr.security.user.session.lifetime'), $session->initTime) < time()) {
                self::sessionLog("session lifetime was expired:" . __LINE__);

                if ($checkKeepSessionCookie) {
                    $_SESSION[__CLASS__][self::SESSION_USER_ID] = 0;
                    $_SESSION[__CLASS__][self::SESSION_HASH] = '';

                    self::restore($checkKeepSessionCookie, $isAutomaticRequest);
                } else {
                    $session->userId = 0;
                    $session->hash = '';
                }
                return;
            }
        }

        session_write_close();
        self::sessionLog("session_write_close():" . __LINE__);
    }

    /**
     * Set special cookies. We could re-create session based on that cookies.
     */
    public static function keepSession()
    {
        $session = self::getInstance();

        $tm = strtotime(Scalr::config('scalr.security.user.session.cookie_lifetime'));

        $setHttpsCookie = filter_has_var(INPUT_SERVER, 'HTTPS');
        $signature = self::createCookieHash($session->userId, $tm, $session->sault, $session->hash);
        $token = CryptoTool::hash("{$signature}:" . $session->hashpwd);

        setcookie('scalr_user_id', $session->userId, $tm, "/", null, $setHttpsCookie, true);
        setcookie('scalr_sault', $session->sault, $tm, "/", null, $setHttpsCookie, true);
        setcookie('scalr_hash', $session->hash, $tm, "/", null, $setHttpsCookie, true);
        setcookie('scalr_expire', $tm, $tm, "/", null, $setHttpsCookie, true);
        setcookie('scalr_signature', $signature, $tm, "/", null, $setHttpsCookie, true);
        setcookie('scalr_token', $token, $tm, "/", null, $setHttpsCookie, false);
        $session->setToken($token);
    }

    /**
     * Check if cookies is valid and isn't expired
     *
     * @return bool
     */
    public static function isCookieKeepSession()
    {
        self::sessionLog("check cookieKeepSession:" . __LINE__);

        // check for session restore
        if (isset($_COOKIE['scalr_user_id']) &&
            isset($_COOKIE['scalr_expire']) &&
            isset($_COOKIE['scalr_sault']) &&
            isset($_COOKIE['scalr_hash']) &&
            isset($_COOKIE['scalr_signature'])
        ) {
            $signature = self::createCookieHash($_COOKIE['scalr_user_id'], $_COOKIE['scalr_expire'], $_COOKIE['scalr_sault'], $_COOKIE['scalr_hash']);
            $hash = self::createHash($_COOKIE['scalr_user_id'], $_COOKIE['scalr_sault']);

            if ($_COOKIE['scalr_expire'] < time()) {
                self::sessionLog("cookie KeepSession was expired:" . __LINE__);
                return false;
            }

            if ($signature == $_COOKIE['scalr_signature'] && $hash == $_COOKIE['scalr_hash']) {
                self::sessionLog("restore session from cookie:" . __LINE__);

                $_SESSION[__CLASS__][self::SESSION_USER_ID] = $_COOKIE['scalr_user_id'];
                $_SESSION[__CLASS__][self::SESSION_SAULT] = $_COOKIE['scalr_sault'];
                $_SESSION[__CLASS__][self::SESSION_HASH] = $_COOKIE['scalr_hash'];
                $_SESSION[__CLASS__][self::SESSION_TOKEN] = $_COOKIE['scalr_token'];
                $_SESSION[__CLASS__][self::SESSION_INIT_TIME] = time();
                $_SESSION[__CLASS__][self::SESSION_LAST_TIME] = time();

                return true;
            }
        }

        return false;
    }

    /**
     * Destroy session (including cookies). If session was created by admin, who logged into user
     * (cookie auth is not equal to session auth), then destroy only session and re-create from cookie
     */
    public static function destroy()
    {
        static::startSession();
        session_regenerate_id(true);
        session_destroy();
        self::sessionLog("session_start/destroy():" . __LINE__);

        if (\Scalr::config('scalr.ui.tender_api_key') != '') {
            @setcookie("tender_email", "", time()-86400, "/");
            @setcookie("tender_expires", "", time()-86400, "/");
            @setcookie("tender_hash", "", time()-86400, "/");
            @setcookie("tender_name", "", time()-86400, "/");
            @setcookie("_tender_session", "", time()-86400, "/");
            @setcookie("anon_token", "", time()-86400, "/");
        }

        $clearKeepSession = true;

        if (isset($_COOKIE['scalr_user_id']) &&
            isset($_COOKIE['scalr_expire']) &&
            isset($_COOKIE['scalr_sault']) &&
            isset($_COOKIE['scalr_hash']) &&
            isset($_COOKIE['scalr_signature'])
        ) {
            $signature = self::createCookieHash($_COOKIE['scalr_user_id'], $_COOKIE['scalr_expire'], $_COOKIE['scalr_sault'], $_COOKIE['scalr_hash']);
            $hash = self::createHash($_COOKIE['scalr_user_id'], $_COOKIE['scalr_sault']);

            if ($signature == $_COOKIE['scalr_signature'] && $hash == $_COOKIE['scalr_hash'] && self::getInstance()->getUserId() != $_COOKIE['scalr_user_id']) {
                $clearKeepSession = false;
            }
        }

        if ($clearKeepSession) {
            $setHttpsCookie = filter_has_var(INPUT_SERVER, 'HTTPS');

            @setcookie("scalr_user_id", "", time() - 86400, "/", null, $setHttpsCookie, true);
            @setcookie("scalr_expire", "", time() - 86400, "/", null, $setHttpsCookie, true);
            @setcookie("scalr_hash", "", time() - 86400, "/", null, $setHttpsCookie, true);
            @setcookie("scalr_sault", "", time() - 86400, "/", null, $setHttpsCookie, true);
            @setcookie("scalr_signature", "", time() - 86400, "/", null, $setHttpsCookie, true);
            @setcookie("scalr_token", "", time() - 86400, "/", null, $setHttpsCookie, false);
        }

        session_write_close();
        self::sessionLog("session_write_close():" . __LINE__);
    }

    public static function sessionLog($log)
    {
        //@file_put_contents("/var/log/scalr/session.log", "[".microtime(true)."][{$_SERVER['REQUEST_URI']}]" . $log . "\n", FILE_APPEND);
    }

    public function isAuthenticated()
    {
        return $this->userId ? true : false;
    }

    /**
     * Checks whether the session is created by Scalr Admin
     *
     * @return   bool Returns true if the session is created by Scalr Admin
     */
    public function isVirtual()
    {
        return $this->ruid > 0;
    }

    /**
     * This method is used to provide getters and setters for the session vars
     *
     * @param   string     $name
     * @param   array      $params
     * @return  string|Scalr_Session
     * @throws  \BadMethodCallException
     */
    public function __call($name, $params)
    {
        if (preg_match('#^(get|set)(.+)$#', $name, $m)) {
            $ref = self::getReflectionClass();

            $property = lcfirst($m[2]);

            $constant = 'SESSION_' . strtoupper(preg_replace('/(?!^)[[:upper:]]+/', '_' . '$0', $property));

            if ($ref->hasConstant($constant)) {
                if ($m[1] == 'get') {
                    return $this->{$property};
                } elseif ($m[1] == 'set') {
                    //set are expected to be here
                    self::startSession();

                    self::sessionLog("session_start():" . __LINE__);

                    $this->{$property} = $params[0];

                    $_SESSION[__CLASS__][$property] = $this->{$property};

                    session_write_close();

                    self::sessionLog("session_write_close():" . __LINE__);

                    return $this;
                }
            }
        }

        throw new \BadMethodCallException(sprintf(
            'Method "%s" does not exist for the class %s', $name, get_class($this)
        ));
    }

    /**
     * Gets effective user identifier
     *
     * @return  int   Returns effective user identifier
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * Gets real user identifier which is used to sign in to Scalr
     *
     * @return   int  Returns real user identifier which is used to sign in to Scalr
     */
    public function getRealUserId()
    {
        return $this->ruid ?: $this->userId;
    }

    /**
     * Gets a reflection class
     *
     * @return ReflectionClass Returns a reflection  class
     */
    private static function getReflectionClass()
    {
        if (self::$refClass === null) {
            self::$refClass = new ReflectionClass(__CLASS__);
        }

        return self::$refClass;
    }
}
