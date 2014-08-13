<?php
namespace Scalr\Tests\Model\Loader;

use Scalr\Model\Loader\MappingLoader;
use Scalr\Tests\TestCase;
use Scalr\Model\AbstractEntity;

/**
 * MappingLoaderTest test
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    4.5.2 (07.03.2014)
 */
class MappingLoaderTest extends TestCase
{

    const CLASS_FIELD = 'Scalr\\Model\\Loader\\Field';
    const CLASS_ENTITY = 'Scalr\\Model\\Loader\\Entity';
    const CLASS_MAPPING_COLUMN = 'Scalr\\Model\\Mapping\\Column';

    public function testConstructor()
    {
        $loader = new MappingLoader();
        $this->assertNotNull($loader);
        $this->assertInternalType('array', $loader->getMappingClasses());
        $this->assertArrayHasKey('GeneratedValue', $loader->getMappingClasses());
    }

    public function testLoad()
    {
        $loader = new MappingLoader();
        $entity = $this->getEntity('Entity1');
        $refClass = new \ReflectionClass(get_class($entity));
        foreach ($refClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $refProperty) {
            /* @var $refProperty \ReflectionProperty */
            $loader->load($refProperty);
            $this->assertObjectHasAttribute('annotation', $refProperty);
            $this->assertInstanceOf(self::CLASS_FIELD, $refProperty->annotation);
            $this->assertInstanceOf(self::CLASS_MAPPING_COLUMN, $refProperty->annotation->column);
            $this->assertNotEmpty($refProperty->annotation->column->name);
        }

        $loader->load($refClass);
        $this->assertObjectHasAttribute('annotation', $refClass);
        $this->assertInstanceOf(self::CLASS_ENTITY, $refClass->annotation);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Tests\TestCase::getFixturesDirectory()
     */
    public function getFixturesDirectory()
    {
        return parent::getFixturesDirectory() . '/Model/Entity';
    }

    /**
     * Gets entity
     *
     * @param   string    $name  The name of the entity class
     * @return  AbstractEntity
     */
    public function getEntity($name)
    {
        $class = 'Scalr\\Tests\\Fixtures\\Model\\Entity\\' . $name;
        return new $class();
    }
}