<?php
namespace Scalr\Service\Aws\Kms\DataType;

use Scalr\Service\Aws\Kms\KmsListDataType;

/**
 * Kms GrantConstraintList
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.9 (24.06.2015)
 */
class GrantConstraintList extends KmsListDataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = array('grantId');

    /**
     * Constructor
     *
     * @param array|GrantConstraintData $aListData GrantConstraintData List
     */
    public function __construct($aListData = null)
    {
        parent::__construct($aListData, ['encryptionContextEquals', 'encryptionContextSubset'], __NAMESPACE__ . '\\GrantConstraintData');
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\DataType\ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'GrantConstraints', $member = true)
    {
        return parent::getQueryArray($uriParameterName, $member);
    }
}