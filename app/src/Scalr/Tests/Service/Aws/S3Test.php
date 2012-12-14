<?php
namespace Scalr\Tests\Service\Aws;

use Scalr\Service\Aws\S3\DataType\AccessControlPolicyData;
use Scalr\Service\Aws;
use Scalr\Service\Aws\S3\DataType\ObjectData;
use Scalr\Service\Aws\S3;
use Scalr\Tests\Service\AwsTestCase;

/**
 * Amazon A3 Test
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     12.11.2012
 */
class S3Test extends AwsTestCase
{

	const TEST_BUCKET_NAME = 'phpunit-test-bucket-2';

	const CLASS_S3 = 'Scalr\\Service\\Aws\\S3';

	const CLASS_S3_BUCKET_DATA = "Scalr\\Service\\Aws\\S3\\DataType\\BucketData";

	const CLASS_S3_BUCKET_LIST = "Scalr\\Service\\Aws\\S3\\DataType\\BucketList";

	const CLASS_S3_OBJECT_DATA = "Scalr\\Service\\Aws\\S3\\DataType\\ObjectData";

	const CLASS_S3_OBJECT_LIST = "Scalr\\Service\\Aws\\S3\\DataType\\ObjectList";

	const CLASS_S3_OWNER_DATA = "Scalr\\Service\\Aws\\S3\\DataType\\OwnerData";

	const CLASS_S3_ACCESS_CONTROL_POLICY_DATA = "Scalr\\Service\\Aws\\S3\\DataType\\AccessControlPolicyData";

	/**
	 * @var S3
	 */
	private $s3;

	/**
	 * {@inheritdoc}
	 * @see Scalr\Tests\Service.AwsTestCase::setUp()
	 */
	public function setUp()
	{
		parent::setUp();
		$this->s3 = $this->getContainer()->aws->s3;
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Tests\Service.AwsTestCase::tearDown()
	 */
	public function tearDown()
	{
		unset($this->s3);
		parent::tearDown();
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Tests\Service.AwsTestCase::getFixtureFilePath()
	 */
	public function getFixtureFilePath($filename)
	{
		return $this->getFixturesDirectory() . '/' . S3::API_VERSION_CURRENT . '/' . $filename;
	}

	/**
	 * Gets S3 Mock
	 *
	 * @param    callback $callback
	 * @return   S3       Returns S3 Mock class
	 */
	public function getS3Mock($callback = null)
	{
		return $this->getServiceInterfaceMock('S3');
	}

	/**
	 * @test
	 */
	public function testFunctionalS3 ()
	{
		if ($this->isSkipFunctionalTests()) {
			$this->markTestSkipped();
		}
		$bucketList = $this->s3->bucket->getList();
		$this->assertInstanceOf(self::CLASS_S3_BUCKET_LIST, $bucketList);
		$this->assertInstanceOf(self::CLASS_S3, $bucketList->getS3());
		$this->assertInstanceOf(self::CLASS_S3_OWNER_DATA, $bucketList->getOwner());

		$bucket = $this->s3->bucket->get(self::TEST_BUCKET_NAME);
		if ($bucket !== null) {
			$bucket->delete();
			unset($bucket);
		}

		//Tests creation of the bucket
		$bucket = $this->s3->bucket->create(self::TEST_BUCKET_NAME, Aws::REGION_AP_SOUTHEAST_1);
		$this->assertInstanceOf(self::CLASS_S3_BUCKET_DATA, $bucket);
		$this->assertInstanceOf(self::CLASS_S3, $bucket->getS3());
		$this->assertEquals(spl_object_hash($bucket), spl_object_hash($this->s3->bucket->get(self::TEST_BUCKET_NAME)));
		$this->assertEquals(self::TEST_BUCKET_NAME, $bucket->bucketName);
		$this->assertNotEmpty($bucket->creationDate);

		//Checks location
		$this->assertEquals(Aws::REGION_AP_SOUTHEAST_1, $bucket->getLocation());

		$acl = $bucket->getAcl();
		$this->assertInstanceOf(self::CLASS_S3_ACCESS_CONTROL_POLICY_DATA, $acl);
		$this->assertNotEmpty($acl->toXml());
		//Checks that generated document is properly constructed.
		$dom = new \DOMDocument();
		$dom->loadXML($acl->getOriginalXml());
		$this->assertEqualXMLStructure($acl->toXml(true), $dom);
		//Applies canned ACL
		$ret = $bucket->setAcl(array('x-amz-acl' => 'authenticated-read'));
		$this->assertTrue($ret);
		$acl2 = $bucket->getAcl();
		$this->assertInstanceOf(self::CLASS_S3_ACCESS_CONTROL_POLICY_DATA, $acl2);
		//Restores acl to previous state
		$ret = $bucket->setAcl($acl);
		$this->assertTrue($ret);
		//Compare restored with its stored value
		$this->assertEqualXMLStructure($bucket->getAcl()->toXml(true), $dom);

		//TODO implement delete object test

		$ret = $bucket->delete();
		$this->assertTrue($ret);
		$this->assertNull($this->s3->bucket->get(self::TEST_BUCKET_NAME));
		unset($bucket);
	}
}
