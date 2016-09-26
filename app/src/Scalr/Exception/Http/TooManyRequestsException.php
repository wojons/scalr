<?php
namespace Scalr\Exception\Http;

/**
 * TooManyRequestsException
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.0.0 (10.06.2014)
 */
class TooManyRequestsException extends HttpException
{
    const HTTP_CODE = 429;

    const HTTP_DESCRIPTION = 'Too Many Requests';
}