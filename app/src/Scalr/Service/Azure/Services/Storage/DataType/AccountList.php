<?php

namespace Scalr\Service\Azure\Services\Storage\DataType;

use Scalr\Service\Azure\DataType\AbstractListDataType;

/**
 * AccountList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.6.8
 *
 */
class AccountList extends AbstractListDataType
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\Azure\DataType.AbstractListDataType::getClass()
     */
    public function getClass()
    {
        return __NAMESPACE__ . '\\AccountData';
    }

}