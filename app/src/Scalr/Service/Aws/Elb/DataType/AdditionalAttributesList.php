<?php
namespace Scalr\Service\Aws\Elb\DataType;

use Scalr\Service\Aws\Elb\AbstractElbListDataType;

/**
 * AdditionalAttributesList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.9
 *
 * @method   AdditionalAttributesData get() get($position = null) Gets AdditionalAttributesData at specified position
 *                                                    in the list.
 */
class AdditionalAttributesList extends AbstractElbListDataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = ['loadBalancerName'];

    /**
     * Constructor
     *
     * @param array|AdditionalAttributesList[] $aListData  Tags List
     */
    public function __construct($aListData = null)
    {
        parent::__construct(
            $aListData,
            ['key', 'value'],
            'Scalr\\Service\\Aws\\Elb\\DataType\\AdditionalAttributesData'
        );
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'AdditionalAttributes', $member = true)
    {
        return parent::getQueryArray($uriParameterName);
    }
}