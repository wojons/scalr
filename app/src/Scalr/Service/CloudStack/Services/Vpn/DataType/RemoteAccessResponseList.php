<?php
namespace Scalr\Service\CloudStack\Services\Vpn\DataType;

use Scalr\Service\CloudStack\DataType\AbstractListDataType;

/**
 * RemoteAccessResponseList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class RemoteAccessResponseList extends AbstractListDataType
{

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\DataType.AbstractListDataType::getClass()
     */
    public function getClass()
    {
        return __NAMESPACE__ . '\\RemoteAccessResponseData';
    }

}