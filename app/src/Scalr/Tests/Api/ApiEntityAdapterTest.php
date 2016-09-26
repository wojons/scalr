<?php

namespace Scalr\Tests\Api;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Tests\Fixtures\Api\DataType\TestApiAdapter;
use Scalr\Tests\Fixtures\Api\DataType\TestApiEntity2Adapter;
use Scalr\Tests\Fixtures\Model\Entity\TestEntity;
use Scalr\Tests\TestCase;
use stdClass;

/**
 * ApiEntityAdapter test
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since  5.11  (11.11.2015)
 */
class ApiEntityAdapterTest extends TestCase
{
    /**
     * Test adapter with setting properties
     *
     * @var TestApiEntity2Adapter
     */
    protected static $entity2Adapter;

    /**
     * Setups test adapter
     */
    public static function setUpBeforeClass()
    {
        static::$entity2Adapter = new TestApiEntity2Adapter(new ApiController());
    }

    /**
     * @test
     */
    public function testToEntity()
    {
        $adapter = new TestApiAdapter(new ApiController());
        $testTime = '2015-11-12';
        $testData = new stdClass();
        $testData->id = rand(1,100);
        $testData->dtField = $testTime;
        /* @var  $entity TestEntity */
        $entity = $adapter->toEntity($testData);
        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertEquals($testData->id, $entity->id);
        $this->assertEquals(new \DateTime($testTime), $entity->dtField);
    }

    /**
     * Test validate object
     *
     * @test
     */
    public function testValidateObject()
    {
        $testRequest = new stdClass();
        $testRequest->data = 'foo';
        $testRequest->queue = 'bar';
        static::$entity2Adapter->validateObject($testRequest);
        //add property that does not exist in entity
        $testRequest->bar = 'foo bar';
        try {
            static::$entity2Adapter->validateObject($testRequest);
            $this->fail('ApiErrorException is expected.');
        } catch (ApiErrorException $e) {
            $this->assertEquals(ErrorMessage::ERR_INVALID_STRUCTURE, $e->getError());
        }
    }

    /**
     * Provider for testValidateForbidSymbol
     *
     * @return array
     */
    public function notValidRequestObjectsProvider()
    {
        $providerData = [];
        $testRequest = new stdClass();
        $testRequest->data =  "><script>'not valid'</script>";
        $providerData[] = [$testRequest];
        $testRequest = new stdClass();
        $testRequest->data = 'foo bar';
        //setting property
        $testRequest->queue = "><script>'not valid'</script>";
        $providerData[] = [$testRequest];
        return $providerData;
    }

    /**
     * The test for the detection of banned symbols in the properties of an object
     *
     * @test
     * @param stdClass $object test request object
     * @dataProvider notValidRequestObjectsProvider()
     * @expectedException \Scalr\Api\Rest\Exception\ApiErrorException
     * @expectedExceptionMessageRegExp /Property \w+ contains invalid characters/
     */
    public function testValidateForbidSymbol($object)
    {
        static::$entity2Adapter->validateObject($object);
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
     * @expectedException \Scalr\Api\Rest\Exception\ApiErrorException
     */
    public function testConvertInputValue($filedType, $value)
    {
        ApiEntityAdapter::convertInputValue($filedType, $value);
    }
}