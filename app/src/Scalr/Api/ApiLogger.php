<?php

namespace Scalr\Api;

use Exception;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Rest\Http\Response;
use Scalr\Util\Logger;

/**
 * ApiLogger
 * Logger implementation for API
 *
 * @author N.V.
 */
class ApiLogger extends Logger
{
    /**
     * Logs failed requests data
     *
     * @param   Request     $request    API request data
     * @param   Response    $response   API response data
     */
    public function logError(Request $request, Response $response)
    {
        if ($this->enabled && !empty($this->writer)) {
            try {
                $time = time();
                $status = $response->getStatus();

                $data = [
                    "tag"      => $this->defaultTag,
                    "dateTime" => $time,
                    "message"  => $status,
                    "extra"    => [
                        'request' => [
                            'remote_ip' => $request->getIp(),
                            'method'    => $request->getMethod(),
                            'url'       => $request->getUrl() . $request->getPath(),
                            'headers'   => $request->headers(),
                            'body'      => $request->getBody()
                        ],
                        'response' => $response->finalize(),
                        'tags'     => [$this->defaultTag, $status],
                        'time'     => $time
                    ],
                    "type" => "ApiLog"
                ];

                $this->writer->send($data);
            } catch (Exception $e) {
                \Scalr::logException(new Exception(sprintf("Api logger could not save the record: %s", $e->getMessage()), $e->getCode(), $e));
            }
        }
    }
}