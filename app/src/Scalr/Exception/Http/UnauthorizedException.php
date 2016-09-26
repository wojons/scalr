<?php
namespace Scalr\Exception\Http;

/**
 * UnauthorizedException
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.0.0 (10.06.2014)
 */
class UnauthorizedException extends HttpException
{
    const HTTP_CODE = 401;

    const HTTP_DESCRIPTION = 'Unauthorized';
}