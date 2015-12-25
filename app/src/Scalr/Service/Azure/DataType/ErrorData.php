<?php

namespace Scalr\Service\Azure\DataType;

/**
 * ErrorData class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
class ErrorData extends AbstractDataType
{
    /**
     * Not found error code
     */
    const ERR_AZURE_NOT_FOUND = 'ResourceNotFound';

    /**
     * Response code
     *
     * @var string
     */
    public $code;

    /**
     * Response message
     *
     * @var string
     */
    public $message;

}