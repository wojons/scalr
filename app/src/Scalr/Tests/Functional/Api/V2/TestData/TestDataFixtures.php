<?php
namespace Scalr\Tests\Functional\Api\V2\TestData;

use DirectoryIterator;
use Scalr\System\Config\Yaml;
use Scalr\Model\Entity\Account\User;
use Scalr\Model\Entity\Account\Environment;

/**
 * Class TestData
 * @package Scalr\Tests\Functional\Api\V2
 */
abstract class TestDataFixtures
{
    /**
     * Regexp for object property prom fixtures
     * property example &{3}ProjectsData or &ProjectsData
     */
    const PROPERTY_REGEXP = '#^&(\{(\d{1,3})\})?(.*)$#';

    /**
     * Test data pointer
     */
    const TEST_DATA = null;

    /**
     * User identifier
     * @var int
     */
    protected static $testUserId;

    /**
     * Test user
     *
     * @var User
     */
    protected static $user;

    /**
     * Environment identifier
     *
     * @var int
     */
    protected static $testEnvId;

    /**
     * Environment instance
     *
     * @var Environment
     */
    protected static $env;

    /**
     * Data from fixtures
     *
     * @var array
     */
    protected $sets = [];

    /**
     * Entity class name
     *
     * @var string
     */
    protected $entityClass;

    /**
     * TestDataFixtures constructor.
     * @param array $sets
     */
    public function __construct(array $sets)
    {
        $this->sets = $sets;
    }

    /**
     * Generate test objects for ApiTestCaseV2
     */
    abstract public function prepareTestData();

    /**
     * Generate data for ApiTestCaseV2
     *
     * @param string $fixtures patch to fixtures directory
     * @return array
     * @throws \Scalr\System\Config\Exception\YamlException
     */
    public static function dataFixtures($fixtures)
    {
        // set config
        static::$testUserId = \Scalr::config('scalr.phpunit.userid');
        static::$user = User::findPk(static::$testUserId);
        static::$testEnvId = \Scalr::config('scalr.phpunit.envid');
        static::$env = Environment::findPk(static::$testEnvId);

        $data = [];
        foreach (new DirectoryIterator($fixtures) as $fileInfo) {
            if ($fileInfo->isFile()) {
                $class = __NAMESPACE__ . '\\' . ucfirst($fileInfo->getBasename('.yaml'));
                if (class_exists($class)) {
                    /* @var $object TestDataFixtures */
                    $object = new $class(Yaml::load($fileInfo->getPathname())->toArray());
                    $object->prepareTestData();
                    $data = array_merge($data, $object->preparePathInfo());
                }
            }
        }
        return $data;
    }

    /**
     * Prepare options for each patch options
     *
     * @return array
     */
    protected function preparePathInfo()
    {
        $paramData = [];
        $this->prepareData(static::TEST_DATA);
        if (array_key_exists('paths', $this->sets)) {
            foreach ($this->sets['paths'] as $pathInfo) {
                foreach ($pathInfo['operations'] as $index => $operation) {
                    $operation = array_merge(['params' => null, 'filterable' => null, 'body' => null], $operation);
                    $paramData[] = [
                        $pathInfo['uri'],
                        $operation['method'],
                        $operation['response'],
                        (array)$this->resolveProperty($operation['params'], $index),
                        (array)$this->resolveProperty($operation['filterable'], $index),
                        (array)$this->resolveProperty($operation['body'], $index),
                        $this->entityClass
                    ];
                }
            }
        } else {
            throw new \InvalidArgumentException('Element paths should exist in fixtures');
        }
        return $paramData;
    }

    /**
     * Check Data if Data has reference another project resolve this data property
     *
     * @param  string $name data name
     */
    protected function prepareData($name)
    {
        if (!empty($this->sets[$name])) {
            foreach ($this->sets[$name] as $index => &$testData) {
                $object = [];
                foreach ($testData as $property => &$value) {
                    $value = $this->resolveProperty($value, $index);
                    if (preg_match('#^(\w*)\.(\w*)$#', $property, $math)) {
                        $propKey = array_pop($math);
                        $propertyName = array_pop($math);
                        $object[$propertyName] = [$propKey => $value];
                        unset($testData[$property]);
                    }
                }
                $testData = array_merge($testData, $object);
            }
        }
    }

    /**
     * If property has reference to the object, will return property or object
     *
     * @param $value string property name
     * @param $index int    property value
     * @return array
     */
    protected function resolveProperty($value, $index)
    {
        if (is_string($value) && preg_match(static::PROPERTY_REGEXP, $value, $matches)) {
            $objectData = explode('.', array_pop($matches));
            $objectName = array_shift($objectData);
            $propValue = array_pop($objectData);

            $index = is_numeric($propIndex = array_pop($matches)) ? $propIndex : $index;
            if (!isset($this->sets[$objectName][$index])) {
                throw new \InvalidArgumentException("$objectName with index $index don't exist in fixtures");
            }

            return ($propValue) ? $this->sets[$objectName][$index][$propValue] : $this->sets[$objectName][$index];
        }
        return $value;
    }

}