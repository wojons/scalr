<?php
namespace Scalr\Service\CloudStack\Services\Volume\DataType;

use Scalr\Service\CloudStack\DataType\AbstractListDataType;

/**
 * VolumeResponseList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class VolumeResponseList extends AbstractListDataType
{

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\DataType.AbstractListDataType::getClass()
     */
    public function getClass()
    {
        return __NAMESPACE__ . '\\VolumeResponseData';
    }

}