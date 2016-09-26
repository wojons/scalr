<?php
namespace Scalr\Tests\Service\Aws;

use Scalr\Service\Aws\Route53;
use Scalr\Tests\Service\AwsTestCase;

/**
 * Amazon Route53 Test
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 */
class Route53Test extends AwsTestCase
{

    const CLASS_ROUTE53 = 'Scalr\\Service\\Aws\\Route53';

    /**
     * {@inheritdoc}
     * @see Scalr\Tests\Service.AwsTestCase::getFixturesDirectory()
     */
    public function getFixturesDirectory()
    {
        return parent::getFixturesDirectory() . '/Route53';
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Tests\Service.AwsTestCase::getFixtureFilePath()
     */
    public function getFixtureFilePath($filename)
    {
        return $this->getFixturesDirectory() . '/' . Route53::API_VERSION_CURRENT . '/' . $filename;
    }

    /**
     * Gets Route53 Mock
     *
     * @param    \Closure|callback|string   $callback
     * @return   Route53      Returns Route53 Mock class
     */
    public function getRoute53Mock($callback = null)
    {
        return $this->getServiceInterfaceMock('Route53', $callback);
    }

    /**
     * Gets response callback
     *
     * @param   string   $method Route53 API method
     * @return  \Closure
     */
    public function getResponseCallback($method)
    {
        $responseMock = $this->getQueryClientResponseMock($this->getFixtureFileContent($method . '.xml'));
        return function() use($responseMock) {
            return $responseMock;
        };
    }

    /**
     * @test
     */
    public function testDescribeHostedZones()
    {
        $route53 = $this->getRoute53Mock($this->getResponseCallback(substr(__FUNCTION__, 4)));
        $list = $route53->zone->describe();

        $this->assertInstanceOf($this->getRoute53ClassName('DataType\\ZoneList'), $list);
        $this->assertInstanceOf(self::CLASS_ROUTE53, $list->getRoute53());
        $this->assertEquals(1, count($list));
        $this->assertEquals(1, $list->maxItems);
        $this->assertEquals('Z222222VVVVVVV', $list->marker);
        $this->assertEquals(true, $list->isTruncated);

    }

    /**
     * @test
     */
    public function testDescribeHealthChecks()
    {
        $route53 = $this->getRoute53Mock($this->getResponseCallback(substr(__FUNCTION__, 4)));
        $list = $route53->health->describe();

        $this->assertInstanceOf($this->getRoute53ClassName('DataType\\HealthList'), $list);
        $this->assertInstanceOf(self::CLASS_ROUTE53, $list->getRoute53());
        $this->assertEquals(1, count($list));
        $this->assertEquals(1, $list->maxItems);
        $this->assertEquals('aaaaaaaa-1234-5678-9012-bbbbbbcccccc', $list->marker);
        $this->assertEquals(true, $list->isTruncated);
    }

    /**
     * @test
     */
    public function testDescribeRecordSets()
    {
        $route53 = $this->getRoute53Mock($this->getResponseCallback(substr(__FUNCTION__, 4)));
        $list = $route53->record->describe('Z1PA6795UKMFR9');

        $this->assertInstanceOf($this->getRoute53ClassName('DataType\\RecordSetList'), $list);
        $this->assertInstanceOf(self::CLASS_ROUTE53, $list->getRoute53());
        $this->assertEquals(1, count($list));
        $this->assertEquals(10, $list->maxItems);
        $this->assertEquals('TestRecordName', $list->nextRecordName);
        $this->assertEquals('TXT', $list->nextRecordType);
        $this->assertEquals(true, $list->isTruncated);

    }

    /**
     * @test
     */
    public function testGetChange()
    {
        $route53 = $this->getRoute53Mock($this->getResponseCallback(substr(__FUNCTION__, 4)));
        $change = $route53->record->fetch('C2682N5HXP0BZ4');

        $this->assertInstanceOf($this->getRoute53ClassName('DataType\\ChangeData'), $change);
        $this->assertInstanceOf(self::CLASS_ROUTE53, $change->getRoute53());
        $this->assertEquals('C2682N5HXP0BZ4', $change->changeId);
        $this->assertEquals('INSYNC', $change->status);

    }

}
