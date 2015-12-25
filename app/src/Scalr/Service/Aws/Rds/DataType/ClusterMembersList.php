<?php

namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\RdsListDataType;

/**
 * ClusterMembersList
 *
 * @author N.V.
 */
class ClusterMembersList extends RdsListDataType
{

    /**
     * Constructor
     *
     * @param array|ClusterMemberData  $aListData List of ClusterMember objects
     */
    public function __construct($aListData = null)
    {
        parent::__construct($aListData, array('dBInstanceIdentifier', 'isClusterWriter', 'dBClusterParameterGroupStatus'), __NAMESPACE__ . '\\ClusterMemberData');
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType\ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'VpcSecurityGroups', $member = true)
    {
        return parent::getQueryArray($uriParameterName, $member);
    }
}