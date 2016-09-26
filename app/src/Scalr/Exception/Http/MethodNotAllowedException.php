<?php
namespace Scalr\Exception\Http;

/**
 * MethodNotAllowedException
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.0.0 (10.06.2014)
 */
class MethodNotAllowedException extends HttpException
{
    const HTTP_CODE = 405;

    const HTTP_DESCRIPTION = 'Method Not Allowed';
}