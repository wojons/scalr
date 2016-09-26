<?php
namespace Scalr\Service\CloudStack\Services\Snapshot\DataType;

use Scalr\Service\CloudStack\DataType\AbstractListDataType;

/**
 * SnapshotPolicyResponseList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class SnapshotPolicyResponseList extends AbstractListDataType
{

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\DataType.AbstractListDataType::getClass()
     */
    public function getClass()
    {
        return __NAMESPACE__ . '\\SnapshotPolicyResponseData';
    }

}