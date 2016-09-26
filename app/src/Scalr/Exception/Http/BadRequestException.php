<?php
namespace Scalr\Exception\Http;

/**
 * BadRequestException
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.0.0 (10.06.2014)
 */
class BadRequestException extends HttpException
{
    const HTTP_CODE = 400;

    const HTTP_DESCRIPTION = 'Bad Request';
}