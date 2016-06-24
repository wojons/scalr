<?php

$windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$sapi_type = php_sapi_name();

$PHPSITE = 'http://php.net/manual/en';

if (substr($sapi_type, 0, 3) == 'cli') {
    $cli = true;
} else {
    $cli = false;
}

$err = array();
$recommend = array();

if (extension_loaded('eaccelerator') || extension_loaded('eAccelerator')) {
    $err[] = "eAccelerator is not compatible with Scalr. It should be disabled.";
}

if (!$windows) {
    // Check POSIX
    if (!function_exists('posix_getpid')) {
        $err[] = "Cannot find posix_getpid function. Make sure that POSIX Functions enabled. Look at $PHPSITE/posix.installation.php";
    }
    // Check PCNTL
    if ($cli && !function_exists('pcntl_fork')) {
        $err[] = "Cannot find pcntl_fork function. Make sure that PCNTL Functions enabled. Look at $PHPSITE/pcntl.installation.php";
    }

    // Check SYSVMSG and System V semaphore
    if (!function_exists('shm_attach') || !function_exists('msg_get_queue')) {
        $err[] = "System V semaphore must be enabled. Look at $PHPSITE/sem.installation.php";
    }
}

// Check PECL_HTTP
if (version_compare(phpversion('http'), '2.5.6', '<')) {
    $err[] = "Version of the pecl_http extension must be greater than or equal 2.5.6. Look at $PHPSITE/http.install.php";
}

//SSH2
if (!function_exists('ssh2_exec')) {
    $err[] = "Ssh2 pecl extension must be installed. Look at $PHPSITE/ssh2.installation.php";
}

//cURL
if (!function_exists('curl_exec')) {
    $err[] = "cURL extension is mandatory and must be installed. Look at $PHPSITE/curl.installation.php";
}

//Socket
if (!function_exists('socket_create')) {
    $err[] = "Sockets must be enabled. Look at $PHPSITE/sockets.installation.php";
}

//YAML
if (!function_exists('yaml_parse')) {
    $err[] = "Yaml extension is required for the application. Look at $PHPSITE/yaml.installation.php";
}

// Check DOM
if (!class_exists('DOMDocument')) {
    $err[] = "Cannot find DOM functions. Make sure that DOM Functions enabled. Look at $PHPSITE/dom.installation.php";
}

// Check SimpleXML
if (!function_exists('simplexml_load_string')) {
    $err[] = "Cannot find simplexml_load_string function. Make sure that SimpleXML Functions enabled. Look at $PHPSITE/simplexml.setup.php";
}

// Check MySQLi
if (!function_exists('mysqli_connect')) {
    $err[] = "Cannot find mysqli_connect function. Make sure that MySQLi Functions enabled. Look at $PHPSITE/mysqli.installation.php";
}

// Check GetText
if (!function_exists('gettext')) {
    $err[] = "Cannot find gettext function. Make sure that GetText Functions enabled. Look at $PHPSITE/gettext.installation.php";
}

// Check MCrypt
if (!function_exists('mcrypt_encrypt')) {
    $err[] = "Cannot find mcrypt_encrypt function. Make sure that mCrypt Functions enabled. Look at $PHPSITE/mcrypt.installation.php";
}

// Check MHash
if (!function_exists('hash')) {
    $err[] = "Cannot find mhash function. Make sure that HASH Functions enabled.";
}

if (!function_exists('json_encode')) {
    $err[] = "Cannot find JSON functions. Make sure that JSON Functions enabled.";
}

// Check OpenSSL
if (!function_exists('openssl_verify')) {
    $err[] = "Cannot find OpenSSL functions. Make sure that OpenSSL Functions enabled.";
}

// Dev requirements
if (!class_exists('ZMQ')) {
    $err[] = "ZMQ is used for some new features and should be installed. Look at $PHPSITE/zmq.requirements.php";
} else if (version_compare(phpversion('zmq'), '1.1.2', '<')) {
    $err[] = "ZMQ is used for some new features. Version of the ZMQ extension must be >= 1.1.2.";
}

if (version_compare(PHP_VERSION, '5.6.13', '<')) {
    //look into phpunit test app/src/Scalr/Tests/SoftwareDependencyTest.php
    $err[] = "You have " . phpversion() . " PHP version must be greater than or equal 5.6.13";
}

// If all extensions are installed
if (count($err) == 0) {
    $cryptokeyPath = __DIR__ . "/../etc/.cryptokey";
    if (!file_exists($cryptokeyPath) || filesize($cryptokeyPath) == 0) {
        if ($windows) {
            $key = '';
            for ($i = 0; $i < 13; ++$i) {
                $key .= sha1(uniqid());
            }
        } else {
            $key = file_get_contents('/dev/urandom', null, null, 0, 512);
        }

        if (strlen($key) < 500) {
            throw new Exception("Null key generated");
        }

        $key = substr(base64_encode($key), 0, 512);
        $res = file_put_contents($cryptokeyPath, $key);
        if ($res == 0) {
            $err[] = "Unable to create etc/.cryptokey file. Please create empty etc/.cryptokey and chmod it to 0777.";
        }
    }

    //Checks cache folder
    $cachePath = __DIR__ . '/../cache';
    if (!is_dir($cachePath)) {
        //Tries to create cache directory automatically
        if (@mkdir($cachePath) === false) {
            $err[] = sprintf(
                'Could not create %s folder automatically. ' .
                'Please create this folder manually with read/write access permissions for webserver and crontab actors.',
                $cachePath
            );
        }
    }

    if (count($err) == 0) {
        $db = null;

        try {
            require_once __DIR__ . '/../src/prepend.inc.php';

            $container = Scalr::getContainer();
            $config = $container->config;
            $db = $container->adodb;
        } catch (Exception $e) {
            $err[] = "Could not initialize bootstrap. " . $e->getMessage();
        }

        try {
            if ($db) {
                $db->Execute("SHOW TABLES");

                $sessionTz = $db->GetOne("SELECT @@session.time_zone");

                $phpTz = date_default_timezone_get();

                $time = gmdate('Y-m-d H:i:s');

                if ($sessionTz != 'SYSTEM' && (new DateTimeZone($sessionTz))->getOffset($time) != (new DateTimeZone($phpTz))->getOffset($time)) {
                    $err[] = "MySQL session timezone ({$sessionTz}) is not system timezone and does not match php timezone ({$phpTz}).";
                }
            }
        } catch (Exception $e) {
            $err[] = "Could not connect to database. Please check credentials in app/etc/config.yml.";
        }
    }
}

if (empty($err)) {
    // Additionally checks conditional packages
    if (!function_exists('ldap_connect')) {
        if ($config('scalr.auth_mode') == 'ldap') {
            $err[] = "LDAP must be enabled if you want to use ldap auth_mode. "
                   . "Look at $PHPSITE/ldap.installation.php";
        } else {
            $recommend[] = "If you're going to use ldap auth_mode, LDAP extension should be enabled. "
                         . "Look at $PHPSITE/ldap.installation.php";
        }
    }
}

$congrats = "Congratulations, your environment settings match Scalr requirements!";
$warningWin = "Please pay attention to the fact that Windows system is not allowed for production environment!";

if (!$cli) {
    if (count($err) == 0) {
        print "<span style='color:green'>" . $congrats . "</span><br>\n";
        if ($windows) {
            print "<span style='color:orange'>" . $warningWin . "</span>\n";
        }
    } else {
        print "<span style='color:red;font-weight:bold;'>Errors:</span><br>";
        foreach ($err as $e)
            print "<span style='color:red'>&bull; {$e}</span><br>";
    }

    if (!empty($recommend)) {
        print "<span style='color:gray;font-weight:bold;'>Recommendations:</span><br>";
        foreach ($recommend as $e)
            print "<span style='color:gray'>&bull; {$e}</span><br>";
    }
} else {
    if (count($err) == 0) {
        print "\033[32m" . $congrats . "\033[0m\n";
        if ($windows) {
            print "\033[31m" . $warningWin . "\033[0m\n";
        }
    } else {
        print "\033[31mErrors:\033[0m\n";
        foreach ($err as $e)
            print "\033[33m- {$e}\033[0m\n";
    }

    if (!empty($recommend)) {
        print "\033[0;33mRecommendations:\033[0m\n";
        foreach ($recommend as $e)
            print "\033[0;33m- {$e}\033[0m\n";
    }
}
