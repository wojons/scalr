<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\RdsListDataType;

/**
 * OptionList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     19.01.2015
 */
class OptionList extends RdsListDataType
{

    /**
     * Constructor
     *
     * @param array|OptionData  $aListData List of OptionData objects
     */
    public function __construct($aListData = null)
    {
        parent::__construct(
            $aListData,
            array('optionName'),
            __NAMESPACE__ . '\\OptionData');
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'Options', $member = true)
    {
        return parent::getQueryArray($uriParameterName, $member);
    }
}