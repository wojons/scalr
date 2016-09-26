<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * ResponseDeleteData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ResponseDeleteData extends AbstractDataType
{

    /**
     * Any text associated with the success or failure
     *
     * @var string
     */
    public $displaytext;

    /**
     * True if operation is executed successfully
     *
     * @var bool
     */
    public $success;


}