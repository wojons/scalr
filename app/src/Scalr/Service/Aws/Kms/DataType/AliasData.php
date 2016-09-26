<?php
namespace Scalr\Service\Aws\Kms\DataType;

use Scalr\Service\Aws\Kms\AbstractKmsDataType;
use Scalr\Service\Aws\KmsException;

/**
 * Kms AliasData
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.9 (24.06.2015)
 */
class AliasData extends AbstractKmsDataType
{
    /**
     * String that contains the key identifier pointed to by the alias.
     *
     * Length constraints: Minimum length of 1. Maximum length of 256.
     * Required: No
     *
     * @var string
     */
    public $targetKeyId;

    /**
     * String that contains the key ARN.
     *
     * Length constraints: Minimum length of 20. Maximum length of 2048.
     * Required: No
     *
     * @var string
     */
    public $aliasArn;

    /**
     * String that contains the alias.
     *
     * Length constraints: Minimum length of 1. Maximum length of 256.
     * Required: No
     * Pattern: ^[a-zA-Z0-9:/_-]+$
     *
     * @var string
     */
    public $aliasName;

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\Kms\AbstractKmsDataType::throwExceptionIfNotInitialized()
     */
    protected function throwExceptionIfNotInitialized()
    {
        parent::throwExceptionIfNotInitialized();

        if ($this->aliasArn === null) {
            throw new KmsException(sprintf(
                'aliasArn property has not been initialized for the "%s" yet',
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
    public function describeKey()
    {
        $this->throwExceptionIfNotInitialized();

        return $this->getKms()->key->describe($this->aliasArn);
    }
}