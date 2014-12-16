<?php

namespace Scalr\Tests\Functional\Ui\Controller\Dashboard\Widget;

use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Tests\WebTestCase;

/**
 * Class StatusTest
 * @author  N.V.
 */
class StatusTest extends WebTestCase
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
    public function testXGetContentAction()
    {
        $uri = '/dashboard/widget/status/xGetContent';
        $locations = $this->getEnvironment()
                          ->getPlatformConfigValue(Ec2PlatformModule::ACCOUNT_TYPE) == Ec2PlatformModule::ACCOUNT_TYPE_GOV_CLOUD
            ? ['us-gov-west-1']
            : [
                'us-east-1',
                'us-west-1',
                'us-west-2',
                'sa-east-1',
                'eu-west-1',
                'eu-central-1',
                'ap-southeast-1',
                'ap-southeast-2',
                'ap-northeast-1'
            ];
        $content = $this->request($uri, ['locations' => $locations], 'POST');

        $this->assertResponseDataHasKeys(array('EC2', 'RDS', 'S3', 'locations'), $content);

        foreach ($content['data'] as $location) {
            $regionName = $location['locations'];

            $this->assertContains($regionName, $locations, "Location '{$regionName}' not found in reuqest!");
        }
    }
}
