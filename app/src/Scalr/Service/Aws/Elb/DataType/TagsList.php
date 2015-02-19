<?php
namespace Scalr\Service\Aws\Elb\DataType;

use Scalr\Service\Aws\Elb\AbstractElbListDataType;

/**
 * TagsList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     12.01.2015
 *
 * @method   TagsData get() get($position = null) Gets TagsData at specified position
 *                                                    in the list.
 */
class TagsList extends AbstractElbListDataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = array(
        'loadBalancerName'
    );

    /**
     * Constructor
     *
     * @param array|TagsList[] $aListData  Tags List
     */
    public function __construct($aListData = null)
    {
        parent::__construct(
            $aListData,
            ['key', 'value'],
            'Scalr\\Service\\Aws\\Elb\\DataType\\TagsData'
        );
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'Tags', $member = true)
    {
        return parent::getQueryArray($uriParameterName);
    }
}