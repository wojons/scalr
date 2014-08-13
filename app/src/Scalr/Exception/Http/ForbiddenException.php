<?php
namespace Scalr\Exception\Http;

/**
 * ForbiddenException
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.0.0 (10.06.2014)
 */
class ForbiddenException extends HttpException
{
    const HTTP_CODE = 403;

    const HTTP_DESCRIPTION = 'Forbidden';
}