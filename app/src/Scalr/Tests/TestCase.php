<?php
namespace Scalr\Tests;

use Scalr\Tests\Constraint\ArrayHas;

/**
 * Basic TestCase class
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     03.12.2012
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase
{

    public static $dbLog = array();

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

    /**
     * Returns true if functional tests should be skipped.
     *
     * @return  bool Returns true if functional tests should be skipped.
     */
    public function isSkipFunctionalTests()
    {
        return \Scalr::config('scalr.phpunit.skip_functional_tests') ? true : false;
    }

    /**
     * Returns fixtures directory
     *
     * @return string Returns fixtures directory
     */
    public function getFixturesDirectory()
    {
        return __DIR__ . '/Fixtures';
    }

    /**
     * Camelizes string
     *
     * @param   string   $input
     * @return  string   Returns camelized string
     */
    public function camelize($input)
    {
        return \Scalr::camelize($input);
    }

    /**
     * Constraints that array has key and value
     *
     * @param   mixed              $value   Expected array value
     * @param   string             $key     Expected array key
     * @param   array|\ArrayAccess $arr     An array which needs to be evaluated
     * @param   string             $message Message
     */
    public static function assertArrayHas($value, $key, $arr, $message = '')
    {
        self::assertThat($arr, new ArrayHas(self::equalTo($value), $key), $message);
    }

    /**
     * Retrieves unique session id.
     *
     * This number is unique per each test execution.
     *
     * @return  string Returns unique session id.
     */
    protected static function getSessionId()
    {
        static $s = null;
        if (!isset($s)) {
            $s = substr(uniqid(), 0, 6);
        }
        return $s;
    }

    /**
     * Retrieves ID of the Scalr installation.
     *
     * It is used for isolation the functional tests of
     * third party services like AWS, OpenStack ... etc
     *
     * @return  string Returns ID of the Scalr installation
     */
    protected static function getInstallationId()
    {
        if (!defined('SCALR_ID')) {
            throw new \Exception('SCALR_ID is not defined!');
        }
        return \SCALR_ID;
    }

    /**
     * Gets test name
     *
     * @param   string $suffix optional Name suffix
     * @return  string Returns test name
     */
    public static function getTestName($suffix = '')
    {
        return 'phpunit' . (!empty($suffix) ? '-' . $suffix : '') . '-' . self::getInstallationId();
    }

    /**
     * Gets reflection method for the specified object
     *
     * @param   object     $object Object
     * @param   string     $method Private or Protected Method name
     * @return  \ReflectionMethod  Returns reflection method for provided object with setAccessible property
     * @throws  \Exception
     */
    public static function getAccessibleMethod($object, $method)
    {
        if (is_object($object)) {
            $class = get_class($object);
        } else {
            throw new \Exception(sprintf(
                'Invalid argument. First parameter must be object, %s given.',
                gettype($object)
            ));
        }

        $ref = new \ReflectionMethod($class, $method);
        $ref->setAccessible(true);

        return $ref;
    }

    /**
     * Gets reflection property for the specified object
     *
     * @param   object     $object   Object
     * @param   string     $property Private or protected property name
     * @return  \ReflectionProperty  Returns reflection property for provided object with setAccessible property
     * @throws  \Exception
     */
    public static function getAccessibleProperty($object, $property)
    {
        if (is_object($object)) {
            $class = get_class($object);
        } else {
            throw new \Exception(sprintf(
                'Invalid argument. First parameter must be object, %s given.',
                gettype($object)
            ));
        }

        $ref = new \ReflectionProperty($class, $property);
        $ref->setAccessible(true);

        return $ref;
    }

    /**
     * Enables mysql log
     */
    public static function enableDbLog()
    {
        global $ADODB_OUTP;
        $ADODB_OUTP = function($msg, $newline) {
            \Scalr\Tests\TestCase::$dbLog[] = str_replace(array('<br>', '-----<hr>'), array("", ""), $msg);
        };
        \Scalr::getDb()->debug = -1;
    }

    /**
     * Disables mysql log
     */
    public static function disableDbLog()
    {
        global $ADODB_OUTP;
        $ADODB_OUTP = null;
        \Scalr::getDb()->debug = false;
    }

    /**
     * Gets mysql database log
     *
     * @return   array Returns logged sql queries
     */
    public static function getDbLog()
    {
        return self::$dbLog;
    }
}