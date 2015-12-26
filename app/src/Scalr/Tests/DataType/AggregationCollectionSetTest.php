<?php
namespace Scalr\Tests\DataType;

use Scalr\Tests\TestCase;
use Scalr\DataType\AggregationCollection;
use Scalr\DataType\AggregationCollectionSet;

/**
 * AggregationCollectionSetTest
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.0 (20.06.2014)
 */
class AggregationCollectionSetTest extends TestCase
{
    /**
     * Data provider for the testLoad
     *
     * @return array
     */
    public function providerLoad()
    {
        $data = [];

        $rawData = [
            ['ccId' => 1, 'projectId' => 2, 'platform' => 'ec2', 'cost' => 0.12],
            ['ccId' => 1, 'projectId' => 3, 'platform' => 'gce', 'cost' => 0.23],
            ['ccId' => 2, 'projectId' => 3, 'platform' => 'ec2', 'cost' => 1.10],
            ['ccId' => 4, 'projectId' => 2, 'platform' => 'gce', 'cost' => 0.13],
        ];

        $data[] = [$rawData, [
            'byCc'       => new AggregationCollection(['ccId'], ['cost' => 'sum']),
            'byProject'  => new AggregationCollection(['projectId'], ['cost' => 'sum']),
            'byPlatform' => new AggregationCollection(['platform'], ['cost' => 'sum']),
        ], [
            'byCc'       => [
                1 => ['id' => 1, 'cost' => 0.35],
                2 => ['id' => 2, 'cost' => 1.10],
                4 => ['id' => 4, 'cost' => 0.13],
            ],
            'byProject'  => [
                2 => ['id' => 2, 'cost' => 0.25],
                3 => ['id' => 3, 'cost' => 1.33]
            ],
            'byPlatform' => [
                'ec2' => ['id' => 'ec2', 'cost' => 1.22],
                'gce' => ['id' => 'gce', 'cost' => 0.36],
            ],
        ]];

        return $data;
    }

    /**
     * @test
     * @dataProvider providerLoad
     */
    public function testLoad($rawData, $set, $expected)
    {
        $collectionSet = new AggregationCollectionSet($set);
        $collectionSet->load($rawData);

        foreach ($collectionSet as $key => $aggregationCollection) {
            /* @var $aggregationCollection \Scalr\DataType\AggregationCollection */
            $this->assertArrayHasKey($key, $expected);
            $this->assertEquals($expected[$key], $aggregationCollection['data']);
        }
    }
}