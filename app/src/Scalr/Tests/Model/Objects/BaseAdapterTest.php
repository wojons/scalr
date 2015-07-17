<?php
namespace Scalr\Tests\Model\Objects;

use Scalr\Tests\TestCase;
use Scalr\Model\Objects\BaseAdapter;
use Scalr\Model\Entity\Os;

/**
 * BaseAdapterTest
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.4.0 (06.05.2014)
 */
class BaseAdapterTest extends TestCase
{
    const ADAPTER_CLASS_NAME = 'Scalr\Model\Objects\BaseAdapter';
    const OS_CLASS_NAME = 'Scalr\Model\Entity\Os';

    /**
     * @test
     */
    public function testGetEntityClass()
    {
        $adapter = new BaseAdapter();

        $prop = new \ReflectionProperty(self::ADAPTER_CLASS_NAME, 'entityClass');
        $prop->setAccessible(true);
        $prop->setValue($adapter, self::OS_CLASS_NAME);

        $this->assertEquals(self::OS_CLASS_NAME, $adapter->getEntityClass());
    }

    /**
     * @test
     */
    public function testGetDataClass()
    {
        $adapter = new BaseAdapter();
        $this->assertEquals('stdClass', $adapter->getDataClass());
    }

    /**
     * @test
     */
    public function testGetRules()
    {
        $rules = [BaseAdapter::RULE_TYPE_TO_DATA => 'id'];
        $adapter = new BaseAdapter();
        $adapter->setRules($rules);

        $this->assertEquals($rules, $adapter->getRules());
    }

    /**
     * Gets fixture
     *
     * @return \Scalr\Model\Entity\Os
     */
    public function getFixtureEntity()
    {
        $entity = new Os();
        $entity->id = 'foo';
        $entity->family = 'family';
        $entity->generation = 'generation';
        $entity->isSystem = 0;
        $entity->name = 'name';
        $entity->status = $entity::STATUS_ACTIVE;
        $entity->version = 'version';

        return $entity;
    }

    /**
     * Provider for testToData test
     *
     * @return array
     */
    public function providerToData()
    {
        $data = [];

        $rules = [
            BaseAdapter::RULE_TYPE_TO_DATA => ['id'],
        ];

        $fixture = $this->getFixtureEntity();

        $data[] = [$rules, $fixture, ['id' => $fixture->id]];

        $rules[BaseAdapter::RULE_TYPE_TO_DATA] = ['id', 'family' => 'myFamilyColumn'];

        $data[] = [$rules, $fixture, ['id' => $fixture->id, 'myFamilyColumn' => $fixture->family]];

        $rules[BaseAdapter::RULE_TYPE_TO_DATA] = null;

        $data[] = [$rules, $fixture, get_object_vars($fixture)];

        return $data;
    }

    /**
     * @test
     * @dataProvider providerToData()
     */
    public function testToData($rules, $entity, $result)
    {
        $adapter = new BaseAdapter();
        $adapter->setEntityClass(self::OS_CLASS_NAME);
        $adapter->setRules($rules);

        $data = $adapter->toData($entity);

        $this->assertEquals($result, (array)$data);
    }

    /**
     * Provider for testToEntity
     *
     * @return array
     */
    public function providerToEntity()
    {
        $me = $this;
        $data = [];

        $rules = [
            BaseAdapter::RULE_TYPE_TO_DATA => ['id'],
        ];

        $fixture = $this->getFixtureEntity();

        $data[] = [$rules, ['id' => $fixture->id], function($entity) use ($me, $fixture) {
            $me->assertInstanceOf(self::OS_CLASS_NAME, $entity);
            $me->assertEquals($fixture->id, $entity->id);
        }];

        $rules[BaseAdapter::RULE_TYPE_TO_DATA] = ['id', 'family' => 'myFamilyColumn'];

        $data[] = [$rules, ['id' => $fixture->id, 'myFamilyColumn' => $fixture->family], function($entity) use ($me, $fixture) {
            $me->assertInstanceOf(self::OS_CLASS_NAME, $entity);
            $me->assertEquals($fixture->id, $entity->id);
            $me->assertEquals($fixture->family, $entity->family);
            $me->assertEmpty($entity->name);
        }];

        $rules[BaseAdapter::RULE_TYPE_TO_DATA] = null;

        $data[] = [$rules, get_object_vars($fixture), function($entity) use ($me, $fixture) {
            $me->assertInstanceOf(self::OS_CLASS_NAME, $entity);
            $me->assertEquals(get_object_vars($fixture), get_object_vars($entity));
        }];

        return $data;
    }

    /**
     * @test
     * @dataProvider providerToEntity()
     */
    public function testToEntity($rules, $data, $cb)
    {
        $adapter = new BaseAdapter();
        $adapter->setEntityClass(self::OS_CLASS_NAME);
        $adapter->setRules($rules);

        $cb($adapter->toEntity($data));
    }

    /**
     * @test
     */
    public function testIterator()
    {
        $rules = [
            BaseAdapter::RULE_TYPE_TO_DATA => ['id'],
        ];

        $adapter = new BaseAdapter();
        $adapter->setEntityClass(self::OS_CLASS_NAME);
        $adapter->setRules($rules);

        $fixture = $this->getFixtureEntity();

        $adapter->setInnerIterator(new \ArrayIterator([$fixture, $this->getFixtureEntity()]));

        $i = 0;
        foreach ($adapter as $data) {
            $this->assertEquals(['id' => $fixture->id], (array)$data);
            $i++;
        }

        $this->assertEquals(2, $i);
    }
}