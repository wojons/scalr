<?php

namespace Scalr\Tests\Model\Entity\Collections;

use Scalr\Model\Entity\Setting;
use Scalr\Model\Entity\SettingEntity;
use Scalr\Tests\Fixtures\Model\Entity\TestEntity2;
use Scalr\Tests\Fixtures\Model\Entity\TestEntitySetting;
use Scalr\Tests\TestCase;

/**
 * SettingsCollection Test
 *
 * @author N.V.
 */
class SettingsCollectionTest extends TestCase
{

    const TEST_TYPE = self::TEST_TYPE_UI;

    protected static $sets;

    /**
     * Removes test data
     */
    public static function tearDownAfterClass()
    {
        $db = \Scalr::getDb();

        $db->Execute("DROP TABLE IF EXISTS `test_abstract_entity_settings`, `test_abstract_entity_2`");
    }

    public function testSettingsDataProvider()
    {
        if (empty(static::$sets)) {
            $db = \Scalr::getDb();

            $db->Execute("
              CREATE TABLE IF NOT EXISTS `test_abstract_entity_2` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `data` VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (`id`))
                ENGINE = InnoDB
            ");

            $db->Execute("
              CREATE TABLE IF NOT EXISTS `test_abstract_entity_settings` (
                `test_entity_id` INT DEFAULT 0 NOT NULL,
                `name` VARCHAR(50) DEFAULT '' NOT NULL,
                `value` LONGTEXT,
                PRIMARY KEY (`test_entity_id`, `name`),
                FOREIGN KEY (`test_entity_id`) REFERENCES `test_abstract_entity_2` (id) ON DELETE CASCADE)
                ENGINE = InnoDB
            ");

            $entity = new TestEntity2();

            $entity->settings['foo'] = 'bar';
            $entity->settings['bar'] = 'foo';
            $entity->settings['foobar'] = 'barfoo';

            $entity->save();

            $set = [$entity->id, [[
                'bar' => 'bar'
            ], [
                'foo' => false
            ]]];
            static::$sets[] = $set;

            $entity = new TestEntity2();

            $entity->settings['foo1'] = 'bar1';
            $entity->settings['bar1'] = 'foo1';
            $entity->settings['foobar1'] = 'barfoo1';

            $entity->save();

            $set = [$entity->id, [[
                'foo1' => 'foo1bar',
                'barbar' => 'foofoo',
                'bar1' => 'foo1'
            ], [
                'bar1' => 'bar1',
                'foofoo' => ''
            ]]];
            static::$sets[] = $set;
        }

        return static::$sets;
    }

    /**
     * @test
     * @functional
     * @dataProvider testSettingsDataProvider
     */
    public function testSaveOnlyModifiedSettings($entityId, $newSettings)
    {
        /* @var $entity TestEntity2 */
        $entity = TestEntity2::findPk($entityId);
        $composition = array_merge($entity->settings->getArrayCopy(), ...$newSettings);

        /* @var $entities TestEntity2[] */
        foreach ($newSettings as $idx => $settings) {
            $entities[$idx] = TestEntity2::findPk($entityId);

            //imitate reading properties
            $entity->settings->load();
        }

        foreach ($newSettings as $idx => $settings) {
            $entity = $entities[$idx];

            foreach ($settings as $name => $value) {
                $entity->settings[$name] = $value;
            }

            $entity->save();
        }

        $entity = TestEntity2::findPk($entityId);
        $settings = $entity->settings->getArrayCopy();

        foreach ($composition as $name => $value) {
            if ($value === false) {
                $this->assertArrayNotHasKey($name, $settings, "Setting '{$name}' not deleted");
            } else {
                $this->assertArrayHasKey($name, $settings, "Missed setting '{$name}'");
                $this->assertEquals($value, $settings[$name], "Setting '{$name}' has wrong value");
            }
        }
    }

    /**
     * @test
     */
    public function testSettingsCollection()
    {
        //test issue #3142
        $entity = new TestEntity2();

        $name = 'foo';

        $entity->settings[$name] = (new TestEntitySetting())->setValue('bar');

        $this->assertEquals($entity->settings->getEntity($name)->name, $name);
    }
}