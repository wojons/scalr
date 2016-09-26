<?php
namespace Scalr\Service\Aws\Kms\DataType;

use Scalr\Service\Aws\Kms\AbstractKmsDataType;
use Scalr\Service\Aws\KmsException;
use Scalr\Service\Aws\Client\ClientException;

/**
 * Kms KeyData
 *
 * Contains information about key entry.
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.9 (22.06.2015)
 */
class KeyData extends AbstractKmsDataType
{
    /**
     * ARN of the key.
     *
     * Length constraints: Minimum length of 20. Maximum length of 2048.
     * Required: No
     *
     * @var string
     */
    public $keyArn;

    /**
     * Unique identifier of the key.
     *
     * Length constraints: Minimum length of 1. Maximum length of 256.
     * Required: No
     *
     * @var string
     */
    public $keyId;


    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\Kms\AbstractKmsDataType::throwExceptionIfNotInitialized()
     */
    protected function throwExceptionIfNotInitialized()
    {
        parent::throwExceptionIfNotInitialized();

        if ($this->keyId === null) {
            throw new KmsException(sprintf(
                'keyId property has not been initialized for the "%s" yet',
                get_class($this)
            ));
        }
    }

    /**
     * DescribeKey API call
     *
     * Provides detailed information about the specified customer master key
     *
     * @return  KeyMetadataData Returns KeyMetadataData
     * @throws  ClientException
     */
    public function describe()
    {
        $this->throwExceptionIfNotInitialized();

        return $this->getKms()->key->describe($this->keyId);
    }

    /**
     * ListKeyPolicies API call
     *
     * Retrieves a list of policies attached to a key.
     *
     * @param   string   $marker     optional Set it to the value of the NextMarker in the response you just received
     * @param   int      $maxRecords optional Maximum number of keys you want listed in the response [1, 1000]
     * @return  PolicyNamesData
     * @throws  ClientException
     */
    public function listPolicies($marker = null, $maxRecords = null)
    {
        $this->throwExceptionIfNotInitialized();

        return $this->getKms()->key->listPolicies($this->keyId, $marker, $maxRecords);
    }

    /**
     * GetKeyPolicy API call
     *
     * Retrieves a policy attached to the specified key.
     *
     * @param   string    $policyName  String that contains the name of the policy.
     *          Currently, this must be "default".
     *          Policy names can be discovered by calling ListKeyPolicies
     *
     * @return  object    Returns a policy document
     * @throws  ClientException
     */
    public function getPolicy($policyName)
    {
        $this->throwExceptionIfNotInitialized();

        return $this->getKms()->key->getPolicy($this->keyId, $policyName);
    }
}