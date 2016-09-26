<?php
namespace Scalr\Tests;

/**
 * Basic TestCase class
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     03.12.2012
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase
{

    /**
     * Scalr UI tests
     */
    const TEST_TYPE_UI = 'ui';

    /**
     * Scalr Rest APIv2 tests
     */
    const TEST_TYPE_API = 'api';

    /**
     * Third party cloud dependent tests
     */
    const TEST_TYPE_CLOUD_DEPENDENT = 'cloud-dependent';

    /**
     * It's supposed to be overridden
     */
    const TEST_TYPE = null;

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
     * @param   string $type  optional The type of the test ui, api, cloud-dependent.
     *          If option is not provided it will try to resolve static::TEST_TYPE constant
     *
     * @return  bool Returns true if functional tests should be skipped.
     */
    public static function isSkippedFunctionalTest($type = null)
    {
        $type = $type ?: static::TEST_TYPE;

        $value = \Scalr::config('scalr.phpunit.functional_tests');

        if (!is_array($value)) {
            return !$value;
        }

        //It silently skips test with undefined type.
        return $type ? !in_array($type, $value) : true;
    }

    /**
     * Returns fixtures directory
     *
     * @return string Returns fixtures directory without trailing slash
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
     * {@inheritdoc}
     * @see PHPUnit_Framework_TestCase::runTest()
     */
    protected function runTest()
    {
        if (isset($this->getAnnotations()['method']['functional']) && static::isSkippedFunctionalTest()) {
            $this->markTestSkipped();
        }

        return parent::runTest();
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
        self::assertArrayHasKey($key, $arr, $message);
        self::assertEquals($value, $arr[$key], $message);
    }

    /**
     * Constraints that object is sub-class of the specified class
     *
     * @param    string    $className The name of the class
     * @param    object    $object    The object to check
     * @param    string    $message   optional The message
     */
    public static function assertSubClassOf($className, $object, $message = '')
    {
        self::assertTrue(is_subclass_of($object, $className), $message);
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