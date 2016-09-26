<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * AvailableProductsList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.0
 *
 */
class AvailableProductsList extends AbstractListDataType
{

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\DataType.AbstractListDataType::getClass()
     */
    public function getClass()
    {
        return __NAMESPACE__ . '\\AvailableProductsData';
    }

}