<?php
namespace Scalr\Tests\Service\Aws;

use Scalr\Service\Aws\Iam;
use Scalr\Tests\Service\AwsTestCase;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\DataType\ErrorData;
use Scalr\Service\Aws\Client\QueryClientException;

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

    const INSTANCE_PROFILE_NAME = 'iprofile';

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
        $roleName = self::getTestName(self::ROLE_NAME);
        $instanceProfileName = self::getTestName(self::INSTANCE_PROFILE_NAME);

        try {
            //Removing previously created instance profiles
            $instanceProfileList = $aws->iam->instanceProfile->describe();
        } catch (QueryClientException $e) {
            if ($e->getErrorData()->getCode() === ErrorData::ERR_ACCESS_DENIED) {
                $this->markTestSkipped("This user is not allowed to list instance profiles");
                return;
            }
            throw $e;
        }

        $this->assertInstanceOf($this->getAwsClassName('Iam\\DataType\\InstanceProfileList'), $instanceProfileList);
        foreach ($instanceProfileList as $instanceProfile) {
            /* @var $instanceProfile \Scalr\Service\Aws\Iam\DataType\InstanceProfileData */
            if ($instanceProfile->instanceProfileName == $instanceProfileName) {
                foreach ($instanceProfile->getRoles() as $r) {
                    $instanceProfile->removeRole($r->roleName);
                }
                $instanceProfile->delete();
                break;
            }
        }
        unset($instanceProfileList);

        //Removing previously created role
        $roleList = $aws->iam->role->describe();
        $this->assertInstanceOf($this->getAwsClassName('Iam\\DataType\\RoleList'), $roleList);
        foreach ($roleList as $role) {
            /* @var $role \Scalr\Service\Aws\Iam\DataType\RoleData */
            if ($role->roleName == $roleName) {
                $res = $aws->iam->role->delete($roleName);
                $this->assertTrue($res);
                break;
            }
        }
        unset($roleList);

        //Creating role
        $role = $aws->iam->role->create($roleName, $assumeRolePolicyDocument);
        $this->assertInstanceOf($this->getAwsClassName('Iam\\DataType\\RoleData'), $role);
        $this->assertEquals($roleName, $role->roleName);

        //Updating Assume Role Policy
        $ret = $role->updateAssumePolicy($assumeRolePolicyDocument);
        $this->assertTrue($ret);

        //Creating a new instance profile
        $profile = $aws->iam->instanceProfile->create($instanceProfileName);
        $this->assertInstanceOf($this->getAwsClassName('Iam\\DataType\\InstanceProfileData'), $profile);
        $this->assertEquals($instanceProfileName, $profile->instanceProfileName);
        $this->assertNotEmpty($profile->instanceProfileId);
        $this->assertNotEmpty($profile->arn);
        $this->assertNotEmpty($profile->createDate);
        $this->assertNotEmpty($profile->path);
        $this->assertEmpty($profile->getRoles()->count());

        //Adding role to instance profile
        $ret = $profile->addRole($role->roleName);
        $this->assertTrue($ret);

        $profile2 = $aws->iam->instanceProfile->fetch($instanceProfileName);
        $this->assertInstanceOf($this->getAwsClassName('Iam\\DataType\\InstanceProfileData'), $profile2);
        $this->assertNotEmpty($profile2->getRoles()->count());
        $this->assertEquals($role->roleName, $profile2->getRoles()->get(0)->roleName);
        unset($profile2);

        //Removing a role from instance profile
        $ret = $profile->removeRole($role->roleName);
        $this->assertTrue($ret);

        //Removing instance profile
        $ret = $profile->delete();
        $this->assertTrue($ret);

        try {
            $aws->iam->instanceProfile->fetch($profile->instanceProfileName);
            $this->assertTrue(false, 'Instance profile is expected to have been removed above.');
        } catch (ClientException $e) {
            if ($e->getErrorData()->getCode() !== ErrorData::ERR_NO_SUCH_ENTITY) {
                $this->assertTrue(false);
            }
            $this->assertTrue(true);
        }

        //Removing role
        $res = $role->delete();
        $this->assertTrue($res);
    }
}
