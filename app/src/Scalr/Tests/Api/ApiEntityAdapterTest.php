<?php

namespace Scalr\Tests\Api;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Tests\Fixtures\Api\DataType\TestApiAdapter;
use Scalr\Tests\Fixtures\Model\Entity\TestEntity;
use Scalr\Tests\TestCase;

/**
 * ApiEntityAdapter test
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since  5.6.14  (11.11.2015)
 */
class ApiEntityAdapterTest extends TestCase
{
    /**
     * @test
     */
    public function testToEntity()
    {
        $adapter = new TestApiAdapter(new ApiController());
        $testTime = '2015-11-12';
        $testData = new \stdClass();
        $testData->id = rand(1,100);
        $testData->dtField = $testTime;
        /* @var  $entity TestEntity */
        $entity = $adapter->toEntity($testData);
        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertEquals($testData->id, $entity->id);
        $this->assertEquals(new \DateTime($testTime), $entity->dtField);
    }

    /**
     * Provider for testConvertInputValue
     *
     * @return array
     */
    public function providerConvertInputValue()
    {
        return [
            ['string', ['foo' => 'bar']],
            ['string', (object)['foo' => 'bar']],
            ['datetime', 'faketime'],
            ['UTCDatetime', '946684800']
        ];

    }

    /**
     * @test
     * @param string $filedType
     * @param mixed  $value     Value what we have to convert
     * @dataProvider providerConvertInputValue()
     * @expectedException Scalr\Api\Rest\Exception\ApiErrorException
     */
    public function testConvertInputValue($filedType, $value)
    {
        ApiEntityAdapter::convertInputValue($filedType, $value);
    }
}