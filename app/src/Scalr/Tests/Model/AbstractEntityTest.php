<?php
namespace Scalr\Tests\Model;

use Scalr\Tests\TestCase;
use Scalr\Tests\Fixtures\Model\Entity\Entity1;

/**
 * AbstractEntityTest test
 *
 * @author   Igor Vodiasov <invar@scalr.com>
 * @since    5.0 (06.05.2014)
 */
class AbstractEntityTest extends TestCase
{
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
}
