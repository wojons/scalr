<?php
namespace Scalr\Service\Aws\Elb\DataType;

use Scalr\Service\Aws\Elb\AbstractElbListDataType;

/**
 * TagDescriptionList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     12.01.2015
 *
 * @method   TagDescriptionData get() get($position = null) Gets TagDescriptionData at specified position
 *                                                    in the list.
 */
class TagDescriptionList extends AbstractElbListDataType
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
     * @param array|TagDescriptionList[] $aListData  Tags List
     */
    public function __construct($aListData = null)
    {
        parent::__construct(
            $aListData,
            ['loadBalancerName', 'tags'],
            'Scalr\\Service\\Aws\\Elb\\DataType\\TagDescriptionData'
        );
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'TagDescriptions', $member = true)
    {
        return parent::getQueryArray($uriParameterName);
    }
}