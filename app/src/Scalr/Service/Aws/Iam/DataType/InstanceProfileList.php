<?php
namespace Scalr\Service\Aws\Iam\DataType;

use Scalr\Service\Aws\Iam\AbstractIamListDataType;

/**
 * InstanceProfileList
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    4.6 (17.12.2013)
 *
 * @property boolean $isTruncated
 *           A flag that indicates whether there are more records to list
 *
 * @property string  $marker
 *           Use this parameter only when paginating results,
 *           and only in a subsequent request after you've received a response where the results are truncated
 *
 *
 * @method   \Scalr\Service\Aws\Iam\DataType\InstanceProfileData get()
 *           get($position = null) Gets InstanceProfileData at specified position in the list.
 */
class InstanceProfileList extends AbstractIamListDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('marker', 'isTruncated');

    /**
     * Constructor
     *
     * @param array|InstanceProfileData  $aListData  InstanceProfileData List
     */
    public function __construct($aListData = null)
    {
        parent::__construct($aListData, array('instanceProfileId', 'instanceProfileName'), __NAMESPACE__ . '\\InstanceProfileData');
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'InstanceProfiles', $member = true)
    {
        return parent::getQueryArray($uriParameterName);
    }
}