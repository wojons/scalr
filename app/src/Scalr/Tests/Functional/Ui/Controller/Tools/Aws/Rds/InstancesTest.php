<?php

namespace Scalr\Tests\Functional\Ui\Controller\Tools\Aws\Rds;

use Scalr\Tests\WebTestCase;

/**
 * Functional test for the Scalr_UI_Controller_Tools_Aws_Rds_Instances class.
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    28.03.2013
 */
class InstancesTest extends WebTestCase
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
    public function testXListInstancesAction()
    {
        $content = $this->request('/tools/aws/rds/instances/xListInstances/?cloudLocation=us-east-1');
        $this->assertResponseDataHasKeys([
            'Engine', 'DBInstanceStatus', 'Endpoint', 'Port', 'DBName', 'MasterUsername', 'DBInstanceClass', 'AllocatedStorage', 'InstanceCreateTime', 'AvailabilityZone', 'isReplica'
        ], $content);
    }

    /**
     * @test
     */
    public function testXGetParametersAction()
    {
        $content = $this->request('/tools/aws/rds/instances/xGetParameters/?cloudLocation=us-east-1');
        $this->assertInternalType('array', $content);
        $this->assertTrue(isset($content['success']) && $content['success']);
        $this->assertArrayHasKey('zones', $content);
        $this->assertArrayHasKey('sgroups', $content);
    }
}