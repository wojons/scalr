<?php
namespace Scalr\Tests;

/**
 * Software dependency test
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     30.10.2012
 */
class SoftwareDependencyTest extends TestCase
{
    /**
     * {@inheritdoc}
     * @see PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
    }

    /**
     * {@inheritdoc}
     * @see PHPUnit_Framework_TestCase::tearDown()
     */
    protected function tearDown()
    {
        parent::tearDown();
    }

    public function testGetScalrzrVersion()
    {
        $dbserver = new \DBServer('fake');
        $rf = new \ReflectionClass('DBServer');
        $rm = $rf->getMethod('GetVersionInfo');
        $rm->setAccessible(true);

        $ret = $rm->invoke($dbserver, '0.7.230');
        $this->assertEquals([0, 7, 230], $ret);

        $ret2 = $rm->invoke($dbserver, '0.9');
        $this->assertEquals([0, 9, 0], $ret2);

        $this->assertTrue($ret2 >= $ret);

        $ret = $rm->invoke($dbserver, '2.5.b3671.2c1d4d2');
        $this->assertEquals([2, 5, 3671], $ret);

        $ret2 = $rm->invoke($dbserver, '2.5.12');
        $this->assertEquals([2, 5, 12], $ret2);

        $this->assertTrue($ret >= $ret2);
    }

    /**
     * Here we should add assertions for all php dependencies which is usded by Scalr.
     *
     * @test
     */
    public function testDependencies()
    {
        $windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $phpBranch = substr(PHP_VERSION, 0, 3);

        $this->assertTrue(
            !($phpBranch == '5.4' && version_compare(PHP_VERSION, '5.4.19', '<')) &&
            !($phpBranch == '5.5' && version_compare(PHP_VERSION, '5.5.4', '<')) &&
            version_compare($phpBranch, '5.4', '>=') ,
            sprintf('You have %s PHP version. It must be >= 5.4.19 for 5.4 branch or >= 5.5.4 for 5.5 branch', PHP_VERSION)
        );

        $this->assertTrue(
            function_exists('hash'),
            'Cannot find mhash function. Make sure that HASH Functions enabled.'
        );

        $this->assertTrue(
            function_exists('json_encode'),
            'Cannot find JSON function. Make sure that JSON Functions enabled.'
        );

        $this->assertTrue(
            function_exists('openssl_verify'),
            'Cannot find OpenSSL functions. Make sure that OpenSSL Functions enabled.'
        );

        $this->assertTrue(
            class_exists('\http\Client'),
            'Pecl_Http extension is required for the application. '
          . 'Please install it https://mdref.m6w6.name/http#Installation:'
        );

        $this->assertTrue(
            version_compare(phpversion('http'), '2.5.3', '>='),
            'Version of the Pecl_Http extension must be at least 2.5.3.'
        );

        $this->assertTrue(
            function_exists('yaml_parse'),
            'Yaml extension is required for the application. '
          . 'Please install it http://php.net/manual/en/yaml.installation.php'
        );

        $this->assertTrue(
            class_exists('mysqli'),
            'Mysqli database driver is mandatory and must be installed. '
          . 'Look at http://php.net/manual/en/mysqli.installation.php'
        );

        $this->assertTrue(
            function_exists('curl_exec'),
            'cURL extension is mandatory and must be installed. '
          . 'Look at http://ua1.php.net/manual/en/curl.installation.php'
        );

        $this->assertTrue(
            function_exists('mcrypt_encrypt'),
            'mcrypt extension is mandatory and must be installed. '
          . 'Look at http://ua1.php.net/manual/en/mcrypt.installation.php'
        );

        $this->assertTrue(
            function_exists('socket_create'),
            'Sockets must be enabled. '
          . 'Look at http://php.net/manual/en/sockets.installation.php'
        );

        $this->assertTrue(
            function_exists('gettext'),
            'Gettext must be enabled. '
          . 'Look at http://php.net/manual/en/gettext.installation.php'
        );

        $this->assertTrue(
            function_exists('simplexml_load_string'),
            'SimpleXML must be enabled. '
          . 'Look at http://ua1.php.net/manual/en/simplexml.setup.php'
        );

        $this->assertTrue(
            function_exists('ssh2_exec'),
            'Ssh2 pecl extension must be installed. '
          . 'Look at http://ua1.php.net/manual/en/ssh2.installation.php'
        );

        $this->assertTrue(
            class_exists('DOMDocument'),
            'DOM must be enabled. '
          . 'Look at http://ua1.php.net/manual/en/dom.installation.php'
        );

        if (!$windows) {
            $this->assertTrue(
                function_exists('shm_attach'),
                'System V semaphore must be enabled. '
              . 'Look at http://www.php.net/manual/en/sem.installation.php'
            );

            $this->assertTrue(
                function_exists('pcntl_fork'),
                'PCNTL extension is mandatory and must be installed. '
              . 'Look at http://www.php.net/manual/en/pcntl.installation.php'
            );

            $this->assertTrue(
                function_exists('posix_getgid'),
                'POSIX must be enabled. '
              . 'Look at http://www.php.net/manual/en/posix.installation.php'
            );

            // $this->assertTrue(
            //     class_exists('SNMP'),
            //     'SNMP must be enabled. '
            //   . 'Look at http://ua1.php.net/manual/en/snmp.installation.php'
            // );

//             $this->assertTrue(
//                 class_exists('RRDUpdater'),
//                 'rrdtool extension must be installed.'
//               . 'Look at http://oss.oetiker.ch/rrdtool/pub/contrib/'
//             );
        }

        /*
        $this->assertTrue(
            class_exists('Mongo'),
            'Mongo extension is required for the application. '
          . 'Please install it http://www.php.net/manual/en/mongo.installation.php'
        );

        $this->assertTrue(
            version_compare(phpversion('mongo'), '1.2.12', '>='),
            'Version of mongodb driver must be greater than or equal 1.2.12'
        );
        */

        //Please add assertions here
    }
}