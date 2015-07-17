<?php
namespace Scalr\Service\Aws\Kms\Handler;

use Scalr\Service\Aws;
use Scalr\Service\Aws\Kms\DataType\KeyList;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\Kms\DataType\KeyMetadataData;
use Scalr\Service\Aws\Kms\DataType\PolicyNamesData;

/**
 * Key hadler of KMS service
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     5.9  (19.06.2015)
 *
 * @method    \Scalr\Service\Aws\Kms\DataType\KeyList list()
 *            list($marker = null, $maxRecords = null)
 *            Lists the customer master keys.
 */
class KeyHandler extends Aws\Kms\AbstractKmsHandler
{
    /**
     * Lists the customer master keys.
     *
     * @param   string   $marker     optional Set it to the value of the NextMarker in the response you just received
     * @param   int      $maxRecords optional Maximum number of keys you want listed in the response [1, 1000]
     * @return  KeyList
     * @throws  ClientException
     */
    private function _list($marker = null, $maxRecords = null)
    {
        return $this->kms->getApiHandler()->listKeys($marker, $maxRecords);
    }

    /**
     * DescribeKey API call
     *
     * Provides detailed information about the specified customer master key
     *
     * @param   string   $keyId  The unique identifier for the customer master key.
     *          This value can be a globally unique identifier, a fully specified ARN to either an alias or a key,
     *          or an alias name prefixed by "alias/".
     *          - Key ARN Example - arn:aws:kms:us-east-1:123456789012:key/12345678-1234-1234-1234-123456789012
     *          - Alias ARN Example - arn:aws:kms:us-east-1:123456789012:alias/MyAliasName
     *          - Globally Unique Key ID Example - 12345678-1234-1234-1234-123456789012
     *          - Alias Name Example - alias/MyAliasName
     *
     * @return  KeyMetadataData Returns KeyMetadataData
     * @throws  ClientException
     */
    public function describe($keyId)
    {
        return $this->kms->getApiHandler()->describeKey($keyId);
    }

    /**
     * ListKeyPolicies API call
     *
     * Retrieves a list of policies attached to a key.
     *
     * @param   string   $keyId      The unique identifier for the customer master key.
     *          This value can be a globally unique identifier, a fully specified ARN to either an alias or a key,
     *          or an alias name prefixed by "alias/".
     *          - Key ARN Example - arn:aws:kms:us-east-1:123456789012:key/12345678-1234-1234-1234-123456789012
     *          - Alias ARN Example - arn:aws:kms:us-east-1:123456789012:alias/MyAliasName
     *          - Globally Unique Key ID Example - 12345678-1234-1234-1234-123456789012
     *          - Alias Name Example - alias/MyAliasName
     *
     * @param   string   $marker     optional Set it to the value of the NextMarker in the response you just received
     * @param   int      $maxRecords optional Maximum number of keys you want listed in the response [1, 1000]
     * @return  PolicyNamesData
     * @throws  ClientException
     */
    public function listPolicies($keyId, $marker = null, $maxRecords = null)
    {
        return $this->kms->getApiHandler()->listKeyPolicies($keyId, $marker, $maxRecords);
    }

    /**
     * GetKeyPolicy API call
     *
     * Retrieves a policy attached to the specified key.
     *
     * @param   string    $keyId       Unique identifier for the customer master key.
     *          This value can be a globally unique identifier or the fully specified ARN to a key.
     *          - Key ARN Example - arn:aws:kms:us-east-1:123456789012:key/12345678-1234-1234-1234-123456789012
     *          - Globally Unique Key ID Example - 12345678-1234-1234-1234-123456789012
     *
     * @param   string    $policyName  String that contains the name of the policy.
     *          Currently, this must be "default".
     *          Policy names can be discovered by calling ListKeyPolicies
     *
     * @return  object    Returns a policy document
     * @throws  ClientException
     */
    public function getPolicy($keyId, $policyName)
    {
        return $this->kms->getApiHandler()->getKeyPolicy($keyId, $policyName);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\AbstractServiceRelatedType::__call()
     */
    public function __call($name, $arguments)
    {
        if ($name == 'list') {
            return call_user_func_array([$this, '_list'], $arguments);
        } else {
            return parent::__call($name, $arguments);
        }
    }
}
