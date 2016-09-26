<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * OsTypeData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class OsTypeData extends AbstractDataType
{

    /**
     * The ID of the OS type
     *
     * @var string
     */
    public $id;

    /**
     * The name/description of the OS type
     *
     * @var string
     */
    public $description;

    /**
     * The ID of the OS category
     *
     * @var string
     */
    public $oscategoryid;

}