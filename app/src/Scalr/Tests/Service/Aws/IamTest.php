<?php
namespace Scalr\Tests\Service\Aws;

use Scalr\Service\Aws\Iam;
use Scalr\Tests\Service\AwsTestCase;

/**
 * Amazon Iam Test
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     13.11.2012
 */
class IamTest extends AwsTestCase
{

    const CLASS_IAM = 'Scalr\\Service\\Aws\\Iam';

    const CLASS_IAM_USER_DATA = 'Scalr\\Service\\Aws\\Iam\\DataType\\UserData';

    const CLASS_IAM_ACCESS_KEY_DATA = 'Scalr\\Service\\Aws\\Iam\\DataType\\AccessKeyData';

    const ROLE_NAME = 'role';

    /**
     * {@inheritdoc}
     * @see Scalr\Tests\Service.AwsTestCase::getFixturesDirectory()
     */
    public function getFixturesDirectory()
    {
        return parent::getFixturesDirectory() . '/Iam';
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Tests\Service.AwsTestCase::getFixtureFilePath()
     */
    public function getFixtureFilePath($filename)
    {
        return $this->getFixturesDirectory() . '/' . Iam::API_VERSION_CURRENT . '/' . $filename;
    }

    /**
     * Gets Iam Mock
     *
     * @param    callback $callback
     * @return   Iam       Returns Iam Mock class
     */
    public function getIamMock($callback = null)
    {
        return $this->getServiceInterfaceMock('Iam');
    }

    /**
     * @test
     */
    public function testFunctionalIam()
    {
        $this->skipIfEc2PlatformDisabled();

        $assumeRolePolicyDocument= '{"Version": "2008-10-17","Statement": [{"Sid": "","Effect": "Deny","Principal": {"Service": "ec2.amazonaws.com"},"Action": "sts:AssumeRole"}]}';

        $aws = $this->getContainer()->aws;
        $aws->setDebug(true);

        $roleName = self::getTestName(self::ROLE_NAME);

        $roleData = $aws->iam->role->create($roleName, $assumeRolePolicyDocument);
        $this->assertInstanceOf($this->getAwsClassName('Iam\\DataType\\RoleData'), $roleData);
        $this->assertEquals($roleName, $roleData->roleName);

        $res = $aws->iam->role->delete($roleName);
        $this->assertTrue($res);
        $aws->resetDebug();
    }
}
