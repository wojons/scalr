<?php

use Scalr\Util\CryptoTool;

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

    /**
     * Identifier of the environment
     *
     * @var int
     */
    private $envId;

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

    const SESSION_ENV_ID  = 'envId';

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
     * @return Scalr_Session
     */
    public static function getInstance()
    {
        if (self::$_session === null) {
            self::$_session = new Scalr_Session();
            self::$_session->hashpwd = CryptoTool::hash(@file_get_contents(APPPATH."/etc/.cryptokey"));
            ini_set('session.cookie_httponly', true);

            if (!filter_has_var(INPUT_COOKIE, session_name()) || !preg_match('/^[-,a-z\d]{1,128}$/i', filter_input(INPUT_COOKIE, session_name()))) {
                self::sessionLog('session is not valid, regenerate');
                session_id(uniqid());
                session_start();
                session_regenerate_id();
                session_write_close();
            }
        }

        if (!self::$_session->restored) {
            self::$_session->restored = true;
            self::restore();

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
                        self::sessionLog("session_id():84");
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
     * Creates a session
     *
     * @param   int  $userId  The effective identifier of the user
     * @param   int  $ruid    optional Real user identifier which is used to sign in to Scalr (Admin UserId)
     */
    public static function create($userId, $ruid = null)
    {
        session_start();
        session_regenerate_id(true);

        self::sessionLog("session_start():101");

        $_SESSION[__CLASS__][self::SESSION_USER_ID] = $userId;
        $_SESSION[__CLASS__][self::SESSION_RUID] = $ruid;

        $sault = CryptoTool::sault();

        $_SESSION[__CLASS__][self::SESSION_SAULT] = $sault;
        $_SESSION[__CLASS__][self::SESSION_HASH] = self::createHash($userId, $sault);

        if (!$ruid) {
            $id = session_id();

            self::sessionLog("session_id():112");

            $hash = self::getInstance()->hashpwd;

            $token = CryptoTool::hash("{$id}:{$hash}");

            $https = filter_has_var(INPUT_SERVER, 'HTTPS');

            $_SESSION[__CLASS__][self::SESSION_TOKEN] = $token;

            setcookie('scalr_token', $token, null, '/', null, $https, false);

            session_write_close();

            self::sessionLog("session_write_close():119");
        }

        self::restore(false);
    }

    protected static function getUserPassword($userId)
    {
        $db = \Scalr::getDb();
        return $db->GetOne('SELECT `password` FROM `account_users` WHERE id = ? LIMIT 1', array($userId));
    }

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

    protected static function createHash($userId, $sault)
    {
        $pass = self::getUserPassword($userId);
        return CryptoTool::hash("{$userId}:{$pass}:" . self::getInstance()->hashpwd . ":{$sault}");
    }

    protected static function createCookieHash($userId, $sault, $hash)
    {
        $pass = self::getUserPassword($userId);
        $userHash = self::getAccountHash($userId);
        return CryptoTool::hash("{$sault}:{$hash}:{$userId}:{$userHash}:{$pass}:" . self::getInstance()->hashpwd);
    }

    protected static function restore($checkKeepSessionCookie = true)
    {
        $session = self::getInstance();

        if (session_status() != PHP_SESSION_ACTIVE) {
            session_start();
            self::sessionLog("session_start():171");
        }

        $refClass = self::getReflectionClass();
        foreach ($refClass->getConstants() as $constname => $constvalue) {
            if (substr($constname, 0, 8) !== 'SESSION_') continue;
            $session->{$constvalue} = isset($_SESSION[__CLASS__][$constvalue]) ?
                $_SESSION[__CLASS__][$constvalue] : null;
        }

        $newhash = self::createHash($session->userId, $session->sault);
        if (! ($newhash == $session->hash && !empty($session->hash))) {
            // reset session (invalid)
            $session->userId = 0;
            $session->hash = '';

            if ($checkKeepSessionCookie && self::isCookieKeepSession()) {
                self::restore(false);

                if ($session->userId) {
                    // we've recovered session, update last login
                    /* @var Scalr\Model\Entity\Account\User $user */
                    if (($user = Scalr\Model\Entity\Account\User::findPk($session->userId))) {
                        $user->lastLogin = new DateTime();
                        $user->save();
                    }
                }
            }
        }

        session_write_close();
        self::sessionLog("session_write_close():190");
    }

    public static function isCookieKeepSession()
    {
        // check for session restore
        if (isset($_COOKIE['scalr_user_id']) &&
            isset($_COOKIE['scalr_sault']) &&
            isset($_COOKIE['scalr_hash']) &&
            isset($_COOKIE['scalr_signature'])
        ) {
            $signature = self::createCookieHash($_COOKIE['scalr_user_id'], $_COOKIE['scalr_sault'], $_COOKIE['scalr_hash']);
            $hash = self::createHash($_COOKIE['scalr_user_id'], $_COOKIE['scalr_sault']);

            if ($signature == $_COOKIE['scalr_signature'] && $hash == $_COOKIE['scalr_hash']) {
                $_SESSION[__CLASS__][self::SESSION_USER_ID] = $_COOKIE['scalr_user_id'];
                $_SESSION[__CLASS__][self::SESSION_SAULT] = $_COOKIE['scalr_sault'];
                $_SESSION[__CLASS__][self::SESSION_HASH] = $_COOKIE['scalr_hash'];
                $_SESSION[__CLASS__][self::SESSION_TOKEN] = $_COOKIE['scalr_token'];

                return true;
            }
        }

        return false;
    }

    public static function destroy()
    {
        session_start();
        session_regenerate_id(true);
        session_destroy();
        self::sessionLog("session_start/destroy():220");

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
            isset($_COOKIE['scalr_sault']) &&
            isset($_COOKIE['scalr_hash']) &&
            isset($_COOKIE['scalr_signature'])
        ) {
            $signature = self::createCookieHash($_COOKIE['scalr_user_id'], $_COOKIE['scalr_sault'], $_COOKIE['scalr_hash']);
            $hash = self::createHash($_COOKIE['scalr_user_id'], $_COOKIE['scalr_sault']);

            if ($signature == $_COOKIE['scalr_signature'] && $hash == $_COOKIE['scalr_hash'] && self::getInstance()->getUserId() != $_COOKIE['scalr_user_id']) {
                $clearKeepSession = false;
            }
        }

        if ($clearKeepSession) {
            $setHttpsCookie = filter_has_var(INPUT_SERVER, 'HTTPS');

            @setcookie("scalr_user_id", "", time() - 86400, "/", null, $setHttpsCookie, true);
            @setcookie("scalr_hash", "", time() - 86400, "/", null, $setHttpsCookie, true);
            @setcookie("scalr_sault", "", time() - 86400, "/", null, $setHttpsCookie, true);
            @setcookie("scalr_signature", "", time() - 86400, "/", null, $setHttpsCookie, true);
            @setcookie("scalr_token", "", time() - 86400, "/", null, $setHttpsCookie, false);
        }

        session_write_close();
        self::sessionLog("session_write_close():258");
    }

    public static function sessionLog($log)
    {
        //@file_put_contents("/var/log/scalr/session.log", "[".microtime(true)."][{$_SERVER['REQUEST_URI']}]" . $log . "\n", FILE_APPEND);
    }

    public static function keepSession()
    {
        $session = self::getInstance();

        $tm = time() + 86400 * 30;

        $setHttpsCookie = filter_has_var(INPUT_SERVER, 'HTTPS');
        $signature = self::createCookieHash($session->userId, $session->sault, $session->hash);
        $token = CryptoTool::hash("{$signature}:" . $session->hashpwd);

        setcookie('scalr_user_id', $session->userId, $tm, "/", null, $setHttpsCookie, true);
        setcookie('scalr_sault', $session->sault, $tm, "/", null, $setHttpsCookie, true);
        setcookie('scalr_hash', $session->hash, $tm, "/", null, $setHttpsCookie, true);
        setcookie('scalr_signature', $signature, $tm, "/", null, $setHttpsCookie, true);
        setcookie('scalr_token', $token, $tm, "/", null, $setHttpsCookie, false);
        $session->setToken($token);
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
                    session_start();
                    self::sessionLog("session_start():311");
                    $this->{$property} = $params[0];
                    $_SESSION[__CLASS__][$property] = $this->{$property};
                    session_write_close();
                    self::sessionLog("session_write_close():316");
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
