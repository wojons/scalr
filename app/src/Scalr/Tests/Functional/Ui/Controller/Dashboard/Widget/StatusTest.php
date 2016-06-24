<?php

namespace Scalr\Tests\Functional\Ui\Controller\Dashboard\Widget;

use Scalr\Model\Entity;
use Scalr\Tests\WebTestCase;
use SERVER_PLATFORMS;

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
                          ->keychain(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE] == Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_GOV_CLOUD
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
                'ap-northeast-1',
                'ap-northeast-2',
            ];
        $content = $this->request($uri, ['locations' => $locations], 'POST');

        $this->assertResponseDataHasKeys(array('EC2', 'RDS', 'S3', 'locations'), $content);

        foreach ($content['data'] as $location) {
            $regionName = $location['locations'];

            $this->assertContains($regionName, $locations, "Location '{$regionName}' not found in reuqest!");
        }
    }
}
