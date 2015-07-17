<?php
namespace Scalr\Service\Aws\Kms\DataType;

use Scalr\Service\Aws\Kms\AbstractKmsDataType;

/**
 * Kms PolicyNamesData
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.9 (23.06.2015)
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
class PolicyNamesData extends AbstractKmsDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('nextMarker', 'truncated');

    /**
     * The list of policy names.
     *
     * @var array
     */
    public $policyNames = [];
}