<?php

namespace Scalr\Tests\Functional\Ui\Controller\Tools\Aws;

use Scalr\Tests\WebTestCase;

/**
 * Functional test for the Scalr_UI_Controller_Tools_Aws_Route53.
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 */
class Route53Test extends WebTestCase
{

    /**
     * {@inheritdoc}
     * @see Scalr\Tests.WebTestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
        $this->markTestSkippedIfPlatformDisabled(\SERVER_PLATFORMS::EC2);
    }

    /**
     * @test
     */
    public function testXListZonesAction()
    {
        $content = $this->request('/tools/aws/route53/hostedzones/xList?cloudLocation=us-east-1');
        $this->assertResponseDataHasKeys(array('zoneId', 'name', 'recordSetCount', 'comment'), $content);
    }

    /**
     * @test
     */
    public function testXListChecksAction()
    {
        $content = $this->request('/tools/aws/route53/healthchecks/xList?cloudLocation=us-east-1');
        $this->assertResponseDataHasKeys(array(
            'healthId', 'ipAddress', 'port', 'protocol', 'hostName', 'searchString', 'requestInterval', 'failureThreshold', 'resourcePath'
        ), $content);
    }

}