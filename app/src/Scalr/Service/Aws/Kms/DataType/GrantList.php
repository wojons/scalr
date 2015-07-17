<?php
namespace Scalr\Service\Aws\Kms\DataType;

use Scalr\Service\Aws\Kms\KmsListDataType;

/**
 * Kms GrantList
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.9 (24.06.2015)
 *
 * @property string $requestId
 *           Identifier of the request
 *
 * @property string $nextMarker
 *           If Truncated is true, this value is present and contains the value to use for the
 *           Marker request parameter in a subsequent pagination request.
 *
 * @property boolean $truncated
 *           A flag that indicates whether there are more items in the list.
 *           If your results were truncated, you can make a subsequent pagination request using the Marker
 *           request parameter to retrieve more keys in the list.
 */
class GrantList extends KmsListDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['requestId', 'nextMarker', 'truncated'];

    /**
     * Constructor
     *
     * @param array|GrantData $aListData GrantData List
     */
    public function __construct($aListData = null)
    {
        parent::__construct($aListData, ['grantId', 'granteePrincipal', 'issuingAccount', 'operations', 'retiringPrincipal', 'constraints'], __NAMESPACE__ . '\\GrantData');
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\DataType\ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'Grants', $member = true)
    {
        return parent::getQueryArray($uriParameterName, $member);
    }
}