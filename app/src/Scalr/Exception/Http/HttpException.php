<?php
namespace Scalr\Exception\Http;

/**
 * HttpException
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.0.0 (10.06.2014)
 */
class HttpException extends \Exception
{
    const HTTP_CODE = 500;

    const HTTP_DESCRIPTION = 'Internal Server Error';

    /**
     * Sends header
     */
    public function sendHeader()
    {
        header("HTTP/1.1 " . static::HTTP_CODE . " " . static::HTTP_DESCRIPTION);
    }

    /**
     * Terminates programm execution and sends a header
     */
    public function terminate()
    {
        $this->sendHeader();

        print $this->getMessage();

        exit;
    }
}