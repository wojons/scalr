<?php
namespace Scalr\Tests\Service\Aws;

use Scalr\Tests\Service\AwsTestCase;
use Scalr\Service\Aws\Kms;
use Scalr\Service\Aws\Kms\DataType\KeyData;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\DataType\ErrorData;

/**
 * Amazon KMS Test
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     5.9 (22.06.2015)
 */
class KmsTest extends AwsTestCase
{

    /**
     * {@inheritdoc}
     * @see \Scalr\Tests\Service\AwsTestCase::getFixturesDirectory()
     */
    public function getFixturesDirectory()
    {
        return parent::getFixturesDirectory() . '/Kms';
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Tests\Service\AwsTestCase::getFixtureFilePath()
     */
    public function getFixtureFilePath($filename)
    {
        return $this->getFixturesDirectory() . '/' . Kms::API_VERSION_CURRENT . '/' . $filename;
    }

    /**
     * Gets KSM class name by specified suffix
     *
     * @param    string  $suffix
     * @return   string  A class name
     */
    public function kmsClass($suffix)
    {
        return 'Scalr\\Service\\Aws\\Kms\\' . $suffix;
    }

    /**
     * @test
     * @functional
     */
    public function testFunctional()
    {
        $this->skipIfEc2PlatformDisabled();

        $aws = $this->getEnvironment()->aws(self::REGION);

        //Tests ListKeys API call
        $keyList = $aws->kms->key->list();
        $this->assertInstanceOf($this->kmsClass('DataType\\KeyList'), $keyList);
        $this->assertNotNull($keyList->truncated);
        $this->assertNotNull($keyList->requestId);

        foreach ($keyList as $key) {
            /* @var $key KeyData */
            $this->assertInstanceOf($this->kmsClass('DataType\\KeyData'), $key);

            //Test DescribeKey API call
            $keyMetadata = $aws->kms->key->describe($key->keyId);
            $this->assertInstanceOf($this->kmsClass('DataType\\KeyMetadataData'), $keyMetadata);
            //Key can be described from the object itself
            $this->assertEquals($keyMetadata, $key->describe());
            //Invokes ListKeyPolicies API call
            $policies = $key->listPolicies();
            $this->assertInstanceOf($this->kmsClass('DataType\\PolicyNamesData'), $policies);

            foreach ($policies->policyNames as $policyName) {
                //For the first policy in the result it tries to get its JSON document
                $policyDocument = $key->getPolicy($policyName);
                $this->assertNotNull($policyDocument);
                $this->assertInternalType('object', $policyDocument);
                break;
            }

            //Tests NotFound exception
            try {
                $key->getPolicy('invalidName');
                $this->assertTrue(false, "NotFound error must have been thrown just above.");
            } catch (ClientException $e) {
                $this->assertEquals(ErrorData::ERR_NOT_FOUND, $e->getErrorData()->getCode());
            }

            //Tests ListGrants API call
            $grants = $aws->kms->grant->list($key->keyId);
            $this->assertInstanceOf($this->kmsClass('DataType\\GrantList'), $grants);
            //TODO test GrantData & GrantList properly

            break;
        }

        //Tests ListAliases API call
        $aliases = $aws->kms->alias->list();
        $this->assertInstanceOf($this->kmsClass('DataType\\AliasList'), $aliases);

        foreach ($aliases as $alias) {
            /* @var $alias Kms\DataType\AliasData */
            //It is possible to issue KeyMetatata from the AliasData
            $keyMetadata = $alias->describeKey();
            $this->assertInstanceOf($this->kmsClass('DataType\\KeyMetadataData'), $keyMetadata);
        }

    }
}