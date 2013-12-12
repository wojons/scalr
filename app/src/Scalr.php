<?php

use Scalr\DependencyInjection\Container;

class Scalr
{
    private static $observersSetuped = false;
    private static $EventObservers = array();
    private static $DeferredEventObservers = array();
    private static $ConfigsCache = array();
    private static $InternalObservable;

    /**
     * Gets DI container
     *
     * @return \Scalr\DependencyInjection\Container
     */
    public static function getContainer()
    {
        return Container::getInstance();
    }

    /**
     * Gets an ADO Database Connection as singleton
     *
     * @param   bool    $forceNewConnection optional Force new connection. (false by default)
     * @return  \ADODB_mysqli
     */
    public static function getDb($forceNewConnection = null)
    {
        return Container::getInstance()->adodb($forceNewConnection);
    }

    /**
     * Gets config value
     *
     * @param  string $name An option name
     * @return mixed  Returns configuration value for the specified key
     */
    public static function config($name)
    {
        //This is only working with yaml config.
        //If you get error here looks like "Call to a member function get() on a non-object",
        //you probably have not migrated your config.ini to config.yml.
        //Please run php app/bin/upgrade_20130624_migrate_config.php
        return Container::getInstance()->config->get($name);
    }

    /**
     * Performs preliminary initialization of the DI container
     */
    public static function initializeContainer()
    {
        Container::reset();
        $container = self::getContainer();

        //Dependency injection container config
        require __DIR__ . '/di.php';
    }

    private static function setupObservers()
    {
        Scalr::AttachObserver(new DBEventObserver());

        Scalr::AttachObserver(new DNSEventObserver());

        Scalr::AttachObserver(new Modules_Platforms_Ec2_Observers_Ebs());
        Scalr::AttachObserver(new Modules_Platforms_Cloudstack_Observers_Cloudstack());

        Scalr::AttachObserver(new MessagingEventObserver());
        Scalr::AttachObserver(new ScalarizrEventObserver());
        Scalr::AttachObserver(new BehaviorEventObserver());

        Scalr::AttachObserver(new Modules_Platforms_Ec2_Observers_Ec2());

        Scalr::AttachObserver(new Modules_Platforms_Ec2_Observers_Eip());
        Scalr::AttachObserver(new Modules_Platforms_Ec2_Observers_Elb());

        Scalr::AttachObserver(new Modules_Platforms_Openstack_Observers_Openstack());

        Scalr::AttachObserver(new MailEventObserver(), true);
        Scalr::AttachObserver(new RESTEventObserver(), true);

        self::$observersSetuped = true;
    }

    /**
     * Attach observer
     *
     * @param EventObserver $observer
     */
    public static function AttachObserver ($observer, $isdeffered = false)
    {
        if ($isdeffered)
            $list = & self::$DeferredEventObservers;
        else
            $list = & self::$EventObservers;

        if (array_search($observer, $list) !== false)
            throw new Exception(_('Observer already attached to class <Scalr>'));

        $list[] = $observer;
    }

    /**
     * Method for multiprocess scripts. We must recreate DB connection created in constructor
     */
    public static function ReconfigureObservers()
    {
        if (!self::$observersSetuped)
            self::setupObservers();

        foreach (self::$EventObservers as &$observer)
        {
            if (method_exists($observer, "__construct"))
                $observer->__construct();
        }
    }

    /**
     * Return observer configuration for farm
     *
     * @param string $farmid
     * @param EventObserver $observer
     * @return DataForm
     */
    private static function GetFarmNotificationsConfig($farmid, $observer)
    {
        $DB = self::getDb();

        // Reconfigure farm settings if changes made
        $farms = $DB->GetAll("SELECT farms.id as fid FROM farms INNER JOIN client_settings ON client_settings.clientid = farms.clientid WHERE client_settings.`key` = 'reconfigure_event_daemon' AND client_settings.`value` = '1'");
        if (count($farms) > 0)
        {
            Logger::getLogger(__CLASS__)->debug("Found ".count($farms)." with new settings. Cleaning cache.");
            foreach ($farms as $cfarmid)
            {
                Logger::getLogger(__CLASS__)->info("Cache for farm {$cfarmid["fid"]} cleaned.");
                self::$ConfigsCache[$cfarmid["fid"]] = false;
            }
        }

        // Update reconfig flag
        $DB->Execute("UPDATE client_settings SET `value`='0' WHERE `key`='reconfigure_event_daemon'");

        // Check config in cache
        if (!self::$ConfigsCache[$farmid] || !self::$ConfigsCache[$farmid][$observer->ObserverName])
        {
            Logger::getLogger(__CLASS__)->debug("There is no cached config for this farm or config updated. Loading config...");

            // Get configuration form
            self::$ConfigsCache[$farmid][$observer->ObserverName] = $observer->GetConfigurationForm();

            // Get farm observer id
            $farm_observer_id = $DB->GetOne("
                SELECT * FROM farm_event_observers
                WHERE farmid=? AND event_observer_name=?
                LIMIT 1
            ",
                array($farmid, get_class($observer))
            );

            // Get Configuration values
            if ($farm_observer_id)
            {
                Logger::getLogger(__CLASS__)->info("Farm observer id: {$farm_observer_id}");

                $config_opts = $DB->Execute("SELECT * FROM farm_event_observers_config
                    WHERE observerid=?", array($farm_observer_id)
                );

                // Set value for each config option
                while($config_opt = $config_opts->FetchRow())
                {
                    $field = self::$ConfigsCache[$farmid][$observer->ObserverName]->GetFieldByName($config_opt['key']);
                    if ($field)
                        $field->Value = $config_opt['value'];
                }
            }
            else
                return false;
        }

        return self::$ConfigsCache[$farmid][$observer->ObserverName];
    }

    /**
     * Fire event
     *
     * @param integer $farmid
     * @param string $event_name
     * @param string $event_message
     */
    public static function FireDeferredEvent (Event $event)
    {
        if (!self::$observersSetuped)
            self::setupObservers();

        try
        {
            // Notify class observers
            foreach (self::$DeferredEventObservers as $observer)
            {
                // Get observer config for farm
                $config = self::GetFarmNotificationsConfig($event->GetFarmID(), $observer);

                // If observer configured -> set config and fire event
                if ($config)
                {
                    $observer->SetConfig($config);
                    $res = call_user_func(array($observer, "On{$event->GetName()}"), $event);
                }
            }
        }
        catch(Exception $e)
        {
            Logger::getLogger(__CLASS__)->fatal("Exception thrown in Scalr::FireDeferredEvent(): ".$e->getMessage());
        }

        return;
    }

    /**
     * File event in database
     *
     * @param  integer $farmid
     * @param  string  $event_name
     */
    public static function FireEvent($farmid, Event $event)
    {
        if (!self::$observersSetuped) {
            self::setupObservers();
        }

        $startTime = microtime(true);

        try {
            $event->SetFarmID($farmid);
            $handledObservers = array();

            // Notify class observers
            foreach (self::$EventObservers as $observer) {
                $observerStartTime = microtime(true);

                $observer->SetFarmID($farmid);
                Logger::getLogger(__CLASS__)->info(sprintf("Event %s. Observer: %s", "On{$event->GetName()}", get_class($observer)));

                if ($event instanceof CustomEvent) {
                    call_user_func(array($observer, "OnCustomEvent"), $event);
                } else {
                    call_user_func(array($observer, "On{$event->GetName()}"), $event);
                }

                $handledObservers[get_class($observer)] = microtime(true) - $observerStartTime;
            }
        } catch (Exception $e) {
            Logger::getLogger(__CLASS__)->fatal(
                sprintf("Exception thrown in Scalr::FireEvent(%s:%s, %s:%s): %s",
                    @get_class($observer),
                    $event->GetName(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getMessage()
                ));
            throw new Exception($e->getMessage());
        }

        $event->handledObservers = $handledObservers;

        self::StoreEvent($farmid, $event, microtime(true) - $startTime);
    }

    /**
     * Store event in database
     *
     * @param integer $farmid
     * @param string $event_name
     */
    public static function StoreEvent($farmid, Event $event, $eventTime = null)
    {
        try
        {
            $DB = self::getDb();

            // Generate event message
            $message = $event->getTextDetails();

            $eventStr = null;
            try {
                $eventStr = serialize($event);
            } catch (Exception $e) {

            }

            if ($event->DBServer)
                $eventServerId = $event->DBServer->serverId;

            //short_message temporary used for time tracking
            // Store event in database
            $DB->Execute("INSERT INTO events SET
                farmid	= ?,
                type	= ?,
                dtadded	= NOW(),
                message	= ?,
                event_object = ?,
                event_id	 = ?,
                event_server_id = ?,
                short_message = ?,
                msg_expected = ?,
                msg_created = ?
                ",
                array($farmid, $event->GetName(), $message, $eventStr, $event->GetEventID(), $eventServerId, $eventTime,
                    $event->msgExpected, $event->msgCreated
            ));
        }
        catch(Exception $e)
        {
            Logger::getLogger(__CLASS__)->fatal(sprintf(_("Cannot store event in database: %s"), $e->getMessage()));
        }
    }


    /**
     * Launches server
     *
     * @param   \ServerCreateInfo       $ServerCreateInfo optional The server create info
     * @param   \DBServer               $DBServer         optional The DBServer object
     * @param   bool                    $delayed          optional
     * @param   string                  $reason           optional
     * @param   \Scalr_Account_User|int $user             optional The Scalr_Account_User object or its unique identifier
     * @return  DBServer|null           Returns the DBServer object on cussess or null otherwise
     */
    public static function LaunchServer(ServerCreateInfo $ServerCreateInfo = null, DBServer $DBServer = null,
                                        $delayed = false, $reason = "", $user = null)
    {
        $db = self::getDb();

        //Ensures handling identifier of the user instead of the object
        if ($user !== null && !($user instanceof \Scalr_Account_User)) {
            try {
                $user = Scalr_Account_User::init()->loadById(intval($user));
            } catch (\Exception $e) {
            }
        }

        if (!$DBServer && $ServerCreateInfo) {
            $ServerCreateInfo->SetProperties(array(
                SERVER_PROPERTIES::SZR_KEY => Scalr::GenerateRandomKey(40),
                SERVER_PROPERTIES::SZR_KEY_TYPE => SZR_KEY_TYPE::ONE_TIME
            ));

            $DBServer = DBServer::Create($ServerCreateInfo, false, true);
        } elseif (!$DBServer && !$ServerCreateInfo) {
            // incorrect arguments
            Logger::getLogger(LOG_CATEGORY::FARM)->error(sprintf("Cannot create server"));
            return null;
        }

        if ($user instanceof \Scalr_Account_User) {
            $DBServer->SetProperties(array(
                SERVER_PROPERTIES::LAUNCHED_BY_ID    => $user->id,
                SERVER_PROPERTIES::LAUNCHED_BY_EMAIL => $user->getEmail(),
            ));
        }

        if ($delayed) {
            $DBServer->status = SERVER_STATUS::PENDING_LAUNCH;
            $DBServer->SetProperty(SERVER_PROPERTIES::LAUNCH_REASON, $reason);
            $DBServer->Save();
            return $DBServer;
        }

        if ($ServerCreateInfo && $ServerCreateInfo->roleId) {
            $dbRole = DBRole::loadById($ServerCreateInfo->roleId);
            if ($dbRole->generation == 1) {
                $DBServer->status = SERVER_STATUS::PENDING_LAUNCH;
                $DBServer->Save();

                $DBServer->SetProperty(SERVER_PROPERTIES::LAUNCH_ERROR, "ami-scripts servers no longer supported");

                return $DBServer;
            }
        }

        try {
            $account = Scalr_Account::init()->loadById($DBServer->clientId);
            $account->validateLimit(Scalr_Limits::ACCOUNT_SERVERS, 1);

            PlatformFactory::NewPlatform($DBServer->platform)->LaunchServer($DBServer);

            $DBServer->status = SERVER_STATUS::PENDING;
            $DBServer->Save();

            try {
                if (!$reason)
                    $reason = $DBServer->GetProperty(SERVER_PROPERTIES::LAUNCH_REASON);

                $DBServer->getServerHistory()->markAsLaunched($reason);
            } catch (Exception $e) {
                Logger::getLogger('SERVER_HISTORY')->error(sprintf("Cannot update servers history: {$e->getMessage()}"));
            }
        } catch (Exception $e) {
            Logger::getLogger(LOG_CATEGORY::FARM)->error(new FarmLogMessage($DBServer->farmId,
                sprintf("Cannot launch server on '%s' platform: %s",
                    $DBServer->platform,
                    $e->getMessage()
                )
            ));

            $DBServer->status = SERVER_STATUS::PENDING_LAUNCH;
            $DBServer->SetProperty(SERVER_PROPERTIES::LAUNCH_ERROR, $e->getMessage());
            $DBServer->Save();
        }

        if ($DBServer->status == SERVER_STATUS::PENDING) {
            Scalr::FireEvent($DBServer->farmId, new BeforeInstanceLaunchEvent($DBServer));
            $DBServer->SetProperty(SERVER_PROPERTIES::LAUNCH_ERROR, "");
        }

        return $DBServer;
    }

    public static function GenerateAPIKeys()
    {
        $key = Scalr::GenerateRandomKey();

        $sault = abs(crc32($key));
        $keyid = dechex($sault).dechex(time());

        $ScalrKey = $key;
        $ScalrKeyID = $keyid;

        return array("id" => $ScalrKeyID, "key" => $ScalrKey);
    }

    /**
     * Generates random key of specified length
     *
     * @param   int    $length optional The length of the key
     * @return  string Returns the random string of specified length
     */
    public static function GenerateRandomKey($length = 128)
    {
        //Is there windows os?
        if (DIRECTORY_SEPARATOR === '\\') {
            //windows os
            $rnd = '';
            $t = ceil($length / 40);
            for ($i = 0; $i < $t; ++$i) {
                $rnd .= sha1(uniqid());
            }
        } else {
            //unix os
            $rnd = file_get_contents('/dev/urandom', null, null, 0, $length);
        }

        $key = substr(base64_encode($rnd), 0, $length);

        return $key;
    }

    public static function GenerateUID($short = false, $startWithLetter = false)
    {
        $pr_bits = false;
        if (!$pr_bits) {
            if (DIRECTORY_SEPARATOR !== '\\' && ($fp = @fopen('/dev/urandom', 'rb')) !== false) {
                $pr_bits .= fread($fp, 16);
                fclose($fp);
            } else {
                // If /dev/urandom isn't available (eg: in non-unix systems), use mt_rand().
                $pr_bits = "";
                for ($cnt = 0; $cnt < 16; $cnt++) {
                    $pr_bits .= chr(mt_rand(0, 255));
                }
            }
        }
        $time_low = bin2hex(substr($pr_bits, 0, 4));
        $time_mid = bin2hex(substr($pr_bits, 4, 2));
        $time_hi_and_version = bin2hex(substr($pr_bits, 6, 2));
        $clock_seq_hi_and_reserved = bin2hex(substr($pr_bits, 8, 2));
        $node = bin2hex(substr($pr_bits, 10, 6));

        /**
         * Set the four most significant bits (bits 12 through 15) of the
         * time_hi_and_version field to the 4-bit version number from
         * Section 4.1.3.
         * @see http://tools.ietf.org/html/rfc4122#section-4.1.3
         */
        $time_hi_and_version = hexdec ( $time_hi_and_version );
        $time_hi_and_version = $time_hi_and_version >> 4;
        $time_hi_and_version = $time_hi_and_version | 0x4000;

        /**
         * Set the two most significant bits (bits 6 and 7) of the
         * clock_seq_hi_and_reserved to zero and one, respectively.
         */
        $clock_seq_hi_and_reserved = hexdec ( $clock_seq_hi_and_reserved );
        $clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved >> 2;
        $clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved | 0x8000;

        if ($short) return sprintf('%012s', $node);

        if ($startWithLetter) {
            if (!preg_match("/^[a-z]+[a-z0-9]*$/", $time_low)) {
                $rand_char = chr(rand(97, 102));
                $time_low = $rand_char . substr($time_low, 1);
            }
        }

        return sprintf('%08s-%04s-%04x-%04x-%012s', $time_low, $time_mid, $time_hi_and_version, $clock_seq_hi_and_reserved, $node);
    }

    /**
     * Scalr error handler
     *
     * @param   int        $errno
     * @param   string     $errstr
     * @param   string     $errfile
     * @param   int        $errline
     * @throws  \Exception
     */
    public static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        // Handles error suppression.
        if (0 === error_reporting()) {
            return false;
        }

        $logfile = '/var/log/php-warnings.log';
        $date = date("Y-m-d H:i:s");

        //Friendly error name
        switch ($errno) {
            case E_NOTICE:
                $errname = 'E_NOTICE';
                break;
            case E_WARNING:
                $errname = 'E_WARNING';
                break;
            case E_USER_DEPRECATED:
                $errname = 'E_USER_DEPRECATED';
                break;
            case E_STRICT:
                $errname = 'E_STRICT';
                break;
            case E_USER_NOTICE:
                $errname = 'E_USER_NOTICE';
                break;
            case E_USER_WARNING:
                $errname = 'E_USER_WARNING';
                break;
            case E_COMPILE_ERROR:
                $errname = 'E_COMPILE_ERROR';
                break;
            case E_COMPILE_WARNING:
                $errname = 'E_COMPILE_WARNING';
                break;
            case E_CORE_ERROR:
                $errname = 'E_CORE_ERROR';
                break;
            case E_CORE_WARNING:
                $errname = 'E_CORE_WARNING';
                break;
            case E_DEPRECATED:
                $errname = 'E_DEPRECATED';
                break;
            case E_ERROR:
                $errname = 'E_ERROR';
                break;
            case E_PARSE:
                $errname = 'E_PARSE';
                break;
            case E_RECOVERABLE_ERROR:
                $errname = 'E_RECOVERABLE_ERROR';
                break;
            case E_USER_ERROR:
                $errname = 'E_USER_ERROR';
                break;
            default:
                $errname = $errno;
        }

        $message = "Error {$errname} {$errstr}, in {$errfile}:{$errline}\n";

        switch ($errno) {
            case E_USER_NOTICE:
            case E_NOTICE:
                //Ignore for a while.
                break;

            case E_CORE_ERROR:
            case E_ERROR:
            case E_USER_ERROR:
                $exception = new \Exception($message, $errno);
                $message = $date . " " . $message . "Backtrace:\n " . str_replace("\n#", "\n  #", $exception->getTraceAsString()) . "\n\n";
                @error_log($message, 3, $logfile);
                throw $exception;
                break;

            case E_WARNING:
            case E_USER_WARNING:
            case E_RECOVERABLE_ERROR:
                $exception = new \Exception($message, $errno);
                $message = $message . "Backtrace:\n  " . str_replace("\n#", "\n  #", $exception->getTraceAsString()) . "\n\n";
            default:
                @error_log($date . " " . $message, 3, $logfile);
                break;
        }
    }

    /**
     * Camelizes string
     *
     * @param   string   $input  A string to camelize
     * @return  string   Returns Camelized string
     */
    public static function camelize($input)
    {
        $u = preg_replace_callback('/(_|^)([^_]+)/', function($c){
            return ucfirst(strtolower($c[2]));
        }, $input);
        return $u;
    }

    /**
     * Decamelizes a string
     *
     * @param   string    $str A string
     * @return  string    Returns decamelized string
     */
    public static function decamelize($str)
    {
        return strtolower(preg_replace_callback('/([a-z])([A-Z]+)/', function ($m) {
            return $m[1] . '_' . $m[2];
        }, $str));
    }
}
