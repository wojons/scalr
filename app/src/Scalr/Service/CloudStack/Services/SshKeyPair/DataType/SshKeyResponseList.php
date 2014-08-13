<?php
namespace Scalr\Service\CloudStack\Services\SshKeyPair\DataType;

use Scalr\Service\CloudStack\DataType\AbstractListDataType;

/**
 * SshKeyResponseList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class SshKeyResponseList extends AbstractListDataType
{

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\DataType.AbstractListDataType::getClass()
     */
    public function getClass()
    {
        return __NAMESPACE__ . '\\SshKeyResponseData';
    }

}