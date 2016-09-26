<?php
namespace Scalr\Service\Aws\Iam\DataType;

use Scalr\Service\Aws\Iam\AbstractIamListDataType;

/**
 * RoleList
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    4.6 (16.12.2013)
 *
 * @property boolean $isTruncated
 *           A flag that indicates whether there are more records to list
 *
 * @property string  $marker
 *           Use this parameter only when paginating results,
 *           and only in a subsequent request after you've received a response where the results are truncated
 *
 *
 * @method   \Scalr\Service\Aws\Iam\DataType\RoleData get()
 *           get($position = null) Gets RoleData at specified position in the list.
 */
class RoleList extends AbstractIamListDataType
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
     * @param array|RoleData  $aListData  RoleData List
     */
    public function __construct($aListData = null)
    {
        parent::__construct($aListData, array('roleId', 'roleName'), __NAMESPACE__ . '\\RoleData');
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'Roles', $member = true)
    {
        return parent::getQueryArray($uriParameterName);
    }
}