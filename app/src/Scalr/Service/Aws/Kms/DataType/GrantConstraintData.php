<?php
namespace Scalr\Service\Aws\Kms\DataType;

use Scalr\Service\Aws\Kms\AbstractKmsDataType;

/**
 * Kms GrantConstraintData
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.9 (24.06.2015)
 */
class GrantConstraintData extends AbstractKmsDataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = array('grantId');

    /**
     * The constraint contains additional key/value pairs that serve to further limit the grant.
     *
     * @var string
     */
    public $encryptionContextEquals;

    /**
     * The constraint equals the full encryption context.
     *
     * @var string
     */
    public $encryptionContextSubset;
}