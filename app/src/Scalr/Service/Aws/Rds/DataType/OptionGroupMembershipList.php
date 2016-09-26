<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\RdsListDataType;

/**
 * OptionGroupMembershipList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     19.01.2015
 */
class OptionGroupMembershipList extends RdsListDataType
{

    /**
     * Constructor
     *
     * @param array|OptionGroupMembershipData  $aListData List of OptionGroupMembershipList objects
     */
    public function __construct($aListData = null)
    {
        parent::__construct(
            $aListData,
            array('optionGroupName'),
            __NAMESPACE__ . '\\OptionGroupMembershipData');
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'OptionGroupNames', $member = true)
    {
        return parent::getQueryArray($uriParameterName, $member);
    }
}