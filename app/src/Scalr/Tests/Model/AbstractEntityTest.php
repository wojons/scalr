<?php
namespace Scalr\Tests\Model;

use ADODB_mysqli;
use ADORecordSet_mysqli;
use Exception;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Collections\EntityIterator;
use Scalr\Tests\Fixtures\Model\Entity\TestEntity;
use Scalr\Tests\TestCase;
use Scalr\Tests\Fixtures\Model\Entity\Entity1;
use Scalr\Model\Entity\CloudLocation;

/**
 * AbstractEntityTest test
 *
 * @author   Igor Vodiasov <invar@scalr.com>
 * @since    5.0 (06.05.2014)
 */
class AbstractEntityTest extends TestCase
{
    const CL_LOC_ID = '00000001-0001-0001-0001-000000000001';
    const CL_PLATFORM = 'test';
    const CL_URL = '';
    const CL_NAME = 'test';

    /**
     * Data provider for testBuildQuery
     *
     * @return array
     */
    public function providerBuildQuery()
    {
        $array = [
            [[['os' => null]], 'AND', "`prices`.`os` IS NULL"],
            [[['os' => ['$lt' => null]]], 'AND', "`prices`.`os` IS NOT NULL"],
            [[['os' => ['$gt' => null]]], 'AND', "`prices`.`os` IS NOT NULL"],
            [[['os' => ['$gte' => null]]], 'AND', "`prices`.`os` IS NULL"],
            [[['os' => ['$lte' => null]]], 'AND', "`prices`.`os` IS NULL"],
            [[['priceId' => 1], ['os' =>['$ne' => 0]]], 'AND', "`prices`.`price_id` = '1' AND `prices`.`os` <> '0'"],
            [[['priceId' => 1], ['os' =>['$ne' => null]]], 'AND', "`prices`.`price_id` = '1' AND `prices`.`os` IS NOT NULL"],
            [[['priceId' => 1]], 'AND', "`prices`.`price_id` = '1'"],
            [[['priceId' => 1], ['priceId' => 2]], 'AND', "`prices`.`price_id` = '1' AND `prices`.`price_id` = '2'"],
            [[['priceId' => 1], ['priceId' => 2]], 'OR', "(`prices`.`price_id` = '1' OR `prices`.`price_id` = '2')"],
            [[['priceId' => 1], ['os' => '1'], ['$or' => [['cost' => '123'], ['cost' => '234']]], ['name' => ['$like' => '%test']]], 'AND',
                "`prices`.`price_id` = '1' AND `prices`.`os` = '1' AND (`prices`.`cost` = '123.000000' OR `prices`.`cost` = '234.000000') AND `prices`.`name` LIKE('%test')"
            ],
            [[['priceId' => 1], ['os' => '1'], ['$and' => [['cost' => '123'], ['cost' => '234']]], ['name' => ['$like' => '%test']]], 'OR',
                "(`prices`.`price_id` = '1' OR `prices`.`os` = '1' OR `prices`.`cost` = '123.000000' AND `prices`.`cost` = '234.000000' OR `prices`.`name` LIKE('%test'))"
            ],
            [[['os' => ['$in' => [1, 2, 3, 4]]], ['name' => ['$nin' => ['a1', 'a2', 'a3']]]], 'AND',
                "`prices`.`os` IN('1', '2', '3', '4') AND `prices`.`name` NOT IN('a1', 'a2', 'a3')"
            ],
            [[['os' => ['$in' => [1, 2, 3, 4]]], ['name' => 'a1'], ['cost' => ['$gt' => 1, '$lt' => 3]]], 'AND',
                "`prices`.`os` IN('1', '2', '3', '4') AND `prices`.`name` = 'a1' AND `prices`.`cost` > '1.000000' AND `prices`.`cost` < '3.000000'"
            ],
            [[['os' => ['$in' => [3, 4]]], ['name' => 'a2'], ['$or' => [['cost' => ['$gt' => 5]], ['cost' => ['$lt' => 2]]]]], 'AND',
                "`prices`.`os` IN('3', '4') AND `prices`.`name` = 'a2' AND (`prices`.`cost` > '5.000000' OR `prices`.`cost` < '2.000000')"
            ]
        ];

        return $array;
    }

    /**
     * @test
     * @dataProvider providerBuildQuery
     */
    public function testBuildQuery($criteria, $conjunction, $sql)
    {
        $entity = new Entity1();
        $sqlResult = $entity->_buildQuery($criteria, $conjunction);
        $this->assertEquals(trim($sqlResult['where']), trim($sql));
    }

    /**
     * Creates entity
     *
     * @param    string     $id            optional uuid
     * @param    string     $cloudLocation optional
     * @return   \Scalr\Model\Entity\CloudLocation
     */
    private function getCloudLocationEntity($id = null, $cloudLocation = self::CL_NAME)
    {
        //Create test cloud location
        $cl = new CloudLocation();
        $cl->cloudLocationId = $id ?: self::CL_LOC_ID;

        //Platform,url,cloudLocation is unique key
        $cl->platform = self::CL_PLATFORM;
        $cl->url = self::CL_URL;
        $cl->cloudLocation = self::CL_NAME;

        return $cl;
    }

    /**
     * Removes entity by identifier
     *
     * @param   string    $id  UUID
     */
    private function removeCloudLocationEntityByIdIfExists($id)
    {
        $cl = CloudLocation::findPk($id);
        /* @var $cl \Scalr\Model\Entity\CloudLocation */
        if ($cl instanceof CloudLocation) {
            //Removes previously created location
            $cl->delete();
        }
    }

    /**
     * Cleanups cloud locations
     */
    private function cleanupCloudLocations()
    {
        $cl2Identifier = CloudLocation::calculateCloudLocationId(self::CL_PLATFORM, self::CL_NAME, self::CL_URL);
        $cl3Identifier = CloudLocation::calculateCloudLocationId(self::CL_PLATFORM, self::CL_NAME . '3', self::CL_URL);
        //Removes records
        $this->removeCloudLocationEntityByIdIfExists(self::CL_LOC_ID);
        $this->removeCloudLocationEntityByIdIfExists($cl2Identifier);
        $this->removeCloudLocationEntityByIdIfExists($cl3Identifier);
    }

    /**
     * SCALRCORE-951 we should avoid ON DUPLICATE KEY UPDATE clause on tables with multiple unique indexes
     * @test
     * @functional
     */
    public function testFunctionalSeveralUniqueKeys()
    {
        $db = \Scalr::getDb();

        //Removes previously created records if they exist
        $this->cleanupCloudLocations();

        $cl2Identifier = CloudLocation::calculateCloudLocationId(self::CL_PLATFORM, self::CL_NAME, self::CL_URL);
        $cl3Identifier = CloudLocation::calculateCloudLocationId(self::CL_PLATFORM, self::CL_NAME . '3', self::CL_URL);

        //Creates the first record
        $cl = $this->getCloudLocationEntity();
        $cl->save();
        unset($cl);

        //Checks if it has been saved properly
        $cl = CloudLocation::findPk(self::CL_LOC_ID);
        $this->assertInstanceOf('Scalr\\Model\\Entity\\CloudLocation', $cl);
        $this->assertEquals(self::CL_LOC_ID, $cl->cloudLocationId);
        $this->assertEquals(self::CL_URL, $cl->url);
        $this->assertEquals(self::CL_NAME, $cl->cloudLocation);
        $this->assertEquals(self::CL_PLATFORM, $cl->platform);

        $cl3 = $this->getCloudLocationEntity($cl3Identifier);
        $cl3->cloudLocation = self::CL_NAME . '3';

        $cl3->save();

        //Record with this unique key already exists
        $cl3->cloudLocation = self::CL_NAME;

        //Saving record with existing unique key
        $ex = false;
        try {
            $cl3->save();
            $this->cleanupCloudLocations();
        } catch (Exception $e) {
            //"Duplicate entry 'test--test' for key 'idx_unique'"
            $ex = true;
            $this->assertContains("Duplicate entry", $e->getMessage());
        }
        $this->assertTrue($ex, "Duplicate entry 'test--test' for key 'idx_unique' must be thrown here (3)");

        //Trying to create with the same unique key as $cl but different primary key
        $cl2 = $this->getCloudLocationEntity($cl2Identifier);
        //Checks they're different
        $this->assertNotEquals(self::CL_LOC_ID, $cl2Identifier);

        //unique key should cause error, and should not be just ignored
        $ex = false;
        try {
            $cl2->save();
            $this->cleanupCloudLocations();
        } catch (Exception $e) {
            $ex = true;
            $this->assertContains("Duplicate entry", $e->getMessage());
        }
        $this->assertTrue($ex, "Duplicate entry 'test--test' for key 'idx_unique' must be thrown here (2)");

        $this->assertNull(CloudLocation::findPk($cl2Identifier));

        $this->cleanupCloudLocations();
    }

    /**
     * @test
     * @functional
     */
    public function testResultType()
    {
        $db = \Scalr::getDb();

        $this->createTestEntities($db);

        $count = $db->GetOne("SELECT COUNT(*) FROM `test_abstract_entity`");

        //test RESULT_ENTITY_ITERATOR
        $this->checkResultEntries(
            TestEntity::result(AbstractEntity::RESULT_ENTITY_ITERATOR),
            $count,
            'Scalr\Model\Collections\EntityIterator',
            'Scalr\Tests\Fixtures\Model\Entity\TestEntity'
        );

        //test RESULT_ENTITY_COLLECTION
        $this->checkResultEntries(
            TestEntity::result(AbstractEntity::RESULT_ENTITY_COLLECTION),
            $count,
            'Scalr\Model\Collections\ArrayCollection',
            'Scalr\Tests\Fixtures\Model\Entity\TestEntity'
        );

        //test RESULT_RAW
        $this->checkResultEntries(
            TestEntity::result(AbstractEntity::RESULT_RAW),
            $count,
            '\ADORecordSet_mysqli',
            'array',
            null,
            function (ADORecordSet_mysqli $result) {
                return $result->RowCount();
            }
        );
    }

    private function checkResultEntries(AbstractEntity $entity, $expectedCount, $expectedResultClass, $expectedEntryType, array $criteria = null, callable $countFunction = null)
    {
        $result = $entity->find($criteria);

        $this->assertNotEmpty($result);

        $this->assertInstanceOf($expectedResultClass, $result, get_class($result));

        if ($countFunction === null) {
            $countFunction = 'count';
        }

        $this->assertEquals($expectedCount, $countFunction($result));

        $classType = class_exists($expectedEntryType);

        foreach ($result as $entry) {
            if ($classType) {
                $this->assertInstanceOf($expectedEntryType, $entry, get_class($entry));
            } else {
                $this->assertInternalType($expectedEntryType, $entry, gettype($entry));
            }
        }
    }

    private function createTestEntities($db)
    {
        $db->Execute("
          CREATE TEMPORARY TABLE IF NOT EXISTS `test_abstract_entity` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `str_id` VARCHAR(32) NOT NULL,
            `int_field` INT NULL,
            `string_field` VARCHAR(45) NULL,
            `dt_field` DATETIME NOT NULL,
            `utc_dt_field` DATETIME NOT NULL,
            PRIMARY KEY (`id`, `str_id`))
        ");

        $entity = new TestEntity();
        $entity->strId = 'foo';
        $entity->intField = 100500;
        $entity->save();

        $entity = new TestEntity();
        $entity->strId = 'bar';
        $entity->stringField = 'foobar';
        $entity->save();

        $entity = new TestEntity();
        $entity->strId = 'barfoo';
        $entity->intField = 100500;
        $entity->stringField = 'foobar';
        $entity->save();

        return $entity->table();
    }

    /**
     * @test
     * @functional
     */
    public function testDelete()
    {
        $db = \Scalr::getDb();

        $tableName = $this->createTestEntities($db);

        $count = $db->GetOne("SELECT COUNT(*) FROM {$tableName} WHERE `str_id` = 'foo'");
        $removedCount = TestEntity::deleteBy([['strId' => 'foo']]);
        $this->assertEquals($count, $removedCount);

        $count = $db->GetOne("SELECT COUNT(*) FROM {$tableName} WHERE `str_id` = 'foobar'");
        $removedCount = TestEntity::deleteBy([['strId' => 'foo']]);
        $this->assertEquals($count, $removedCount);

        $entities = TestEntity::findByStrId('bar');
        $removedCount = TestEntity::deleteByStrId('bar');
        $this->assertEquals(count($entities), $removedCount);

        /* @var $entities EntityIterator */
        $entities = TestEntity::all();

        $totalCount = 0;
        /* @var $entity TestEntity */
        foreach ($entities as $entity) {
            $pk = $entity->getIterator()->getPrimaryKey();

            $args = [];

            foreach ($pk as $fieldName) {
                $args[] = $entity->{$fieldName};
            }

            $totalCount += $removedCount = call_user_func_array([get_class($entity), 'deletePk'], $args);
            $this->assertEquals(1, $removedCount);
        }

        $this->assertEquals(count($entities), $totalCount);
        $this->assertEquals(0, $db->GetOne("SELECT COUNT(*) FROM {$tableName}"));
    }
}
