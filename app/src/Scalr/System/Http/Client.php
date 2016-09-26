<?php

namespace Scalr\System\Http;

use http\Client\Request;
use http\Client\Response;

/**
 * PECL http v2 client wrapper
 *
 * @author N.V.
 */
class Client extends \http\Client
{
    /**
     * Sends http request and return response
     *
     * @param Request $request
     *
     * @return Response $response Returns http response
     */
    public function sendRequest(Request $request)
    {
        $response = $this->enqueue($request)->send()->getResponse($request);

        $this->dequeue($request);

        return $response;
    }
}
