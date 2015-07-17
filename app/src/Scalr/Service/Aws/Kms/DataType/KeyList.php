<?php
namespace Scalr\Service\Aws\Kms\DataType;

use Scalr\Service\Aws\Kms\KmsListDataType;

/**
 * Kms KeyList
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    22.06.2015
 *
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
class KeyList extends KmsListDataType
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
     * @param array|KeyData  $aListData KeyData List
     */
    public function __construct($aListData = null)
    {
        parent::__construct($aListData, ['keyId', 'keyArn'], __NAMESPACE__ . '\\KeyData');
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\DataType\ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'Keys', $member = true)
    {
        return parent::getQueryArray($uriParameterName, $member);
    }
}