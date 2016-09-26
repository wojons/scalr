<?php
namespace Scalr\Exception\Http;

/**
 * NotFoundException
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.0.0 (10.06.2014)
 */
class NotFoundException extends HttpException
{
    const HTTP_CODE = 404;

    const HTTP_DESCRIPTION = 'Not Found';
}