<?php

namespace Scalr\Tests\Functional\Ui\Controller;

use Scalr\Tests\WebTestCase;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use SERVER_PLATFORMS;
use Scalr\Model\Entity;

/**
 * Functional test for the Scalr_UI_Controller_Sshkeys class.
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    22.02.2013
 */
class SshkeysTest extends WebTestCase
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
    public function testXListSshKeysAction()
    {
        $uri = '/sshkeys/xList/';
        $content = $this->request($uri);
        $this->assertResponseDataHasKeys(array('id', 'type', 'cloudKeyName', 'farmId', 'platform', 'cloudLocation'), $content);
        if (!empty($content['data'])) {
            $obj = reset($content['data']);
            $sub = $this->request($uri, array(
                'sshKeyId' => $obj['id'],
                'farmId'   => $obj['farmId'],
            ));
            $this->assertInternalType('array', $sub);
            $this->assertEquals($obj, $sub['data'][0]);
        }
    }

    /**
     * @test
     */
    public function testEnvironment()
    {
        $env = $this->getEnvironment();

        if (!$env->isPlatformEnabled(\SERVER_PLATFORMS::EC2)) {
            $this->markTestSkipped(sprintf("EC2 platform is not enabled."));
        }

        $ccProps = $env->keychain(SERVER_PLATFORMS::EC2)->properties;
        $this->assertNotNull($ccProps[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY]);
        $this->assertNotNull($ccProps[Entity\CloudCredentialsProperty::AWS_SECRET_KEY]);
        $this->assertNotNull($ccProps[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY]);
        $this->assertNotNull($ccProps[Entity\CloudCredentialsProperty::AWS_CERTIFICATE]);

//         echo $env->awsAccountNumber . "\n\n";
//         echo $env->awsAccessKeyId . "\n\n";
//         echo $env->awsSecretAccessKey . "\n\n";
//         echo $env->awsCertificate . "\n\n";
//         echo $env->awsPrivateKey . "\n\n";

        $aws = $env->aws('us-east-1');
//        $ret = $aws->validateCertificateAndPrivateKey();
//        $this->assertTrue($ret);
        $this->assertEquals('Query', $aws->ec2->getApiClientType());
        $this->assertInstanceOf('Scalr\\Service\\Aws\\Client\\QueryClient', $aws->ec2->getApiHandler()->getClient());
    }
}