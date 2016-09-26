<?php
namespace Scalr\Service\CloudStack\Services\Snapshot\DataType;

use Scalr\Service\CloudStack\DataType\AbstractListDataType;

/**
 * SnapshotResponseList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class SnapshotResponseList extends AbstractListDataType
{

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\DataType.AbstractListDataType::getClass()
     */
    public function getClass()
    {
        return __NAMESPACE__ . '\\SnapshotResponseData';
    }

}