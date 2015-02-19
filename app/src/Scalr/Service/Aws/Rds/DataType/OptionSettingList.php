<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\RdsListDataType;

/**
 * OptionSettingList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     19.01.2015
 */
class OptionSettingList extends RdsListDataType
{

    /**
     * Constructor
     *
     * @param array|OptionSettingData  $aListData List of OptionSettingData objects
     */
    public function __construct($aListData = null)
    {
        parent::__construct(
            $aListData,
            array('name'),
            __NAMESPACE__ . '\\OptionSettingData');
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'OptionSettings', $member = true)
    {
        return parent::getQueryArray($uriParameterName, $member);
    }
}