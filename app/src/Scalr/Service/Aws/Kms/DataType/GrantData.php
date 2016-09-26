<?php
namespace Scalr\Service\Aws\Kms\DataType;

use Scalr\Service\Aws\Kms\AbstractKmsDataType;

/**
 * Kms GrantData
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.9 (24.06.2015)
 *
 * @property \Scalr\Service\Aws\Kms\DataType\GrantConstraintList $constraints
 */
class GrantData extends AbstractKmsDataType
{
    const OPERATION_DECRYPT = 'Decrypt';
    const OPERATION_ENCRYPT = 'Encrypt';
    const OPERATION_GENERATE_DATA_KEY = 'GenerateDataKey';
    const OPERATION_GENERATE_DATA_KEY_WITHOUT_PLAINTEXT = 'GenerateDataKeyWithoutPlaintext';
    const OPERATION_RE_ENCRYPT_FROM = 'ReEncryptFrom';
    const OPERATION_RE_ENCRYPT_TO = 'ReEncryptTo';
    const OPERATION_CREATE_GRANT = 'CreateGrant';

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('constraints');

    /**
     * Unique grant identifier.
     *
     * Length constraints: Minimum length of 1. Maximum length of 128.
     * Required: No
     *
     * @var string
     */
    public $grantId;

    /**
     * The principal that receives the grant permission.
     *
     * Length constraints: Minimum length of 1. Maximum length of 256.
     * Required: No
     *
     * @var string
     */
    public $granteePrincipal;

    /**
     * The account under which the grant was issued.
     *
     * Length constraints: Minimum length of 1. Maximum length of 256.
     * Required: No
     *
     * @var string
     */
    public $issuingAccount;

    /**
     * List of operations permitted by the grant. This can be any combination of one or more of the following values:
     * 1. Decrypt
     * 2. Encrypt
     * 3. GenerateDataKey
     * 4. GenerateDataKeyWithoutPlaintext
     * 5. ReEncryptFrom
     * 6. ReEncryptTo
     * 7. CreateGrant
     *
     * @var array
     */
    public $operations;

    /**
     * The principal that can retire the account.
     *
     * Length constraints: Minimum length of 1. Maximum length of 256.
     *
     * @var string
     */
    public $retiringPrincipal;
}