<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * ListOsTypesData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class ListOsTypesData extends AbstractDataType
{

    /**
     * List os by description
     *
     * @var string
     */
    public $description;

    /**
     * List by Os type Id
     *
     * @var string
     */
    public $id;

    /**
     * List by keyword
     *
     * @var string
     */
    public $keyword;

    /**
     * List by Os Category id
     *
     * @var string
     */
    public $oscategoryid;

}