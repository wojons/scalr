<?php
namespace Scalr\Exception\Http;

/**
 * NotAcceptableException
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.0.0 (10.06.2014)
 */
class NotAcceptableException extends HttpException
{
    const HTTP_CODE = 406;

    const HTTP_DESCRIPTION = 'Not Acceptable';
}