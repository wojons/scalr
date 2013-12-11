<?php

namespace Scalr\Tests\Functional\Api\V1;

use Scalr\Tests\WebTestCase;
use \Scalr_UI_Request;
use \Scalr_UI_Response;
use \Scalr_Api_Controller;

abstract class ApiTestCase extends WebTestCase
{
    protected $apiDebug = false;

    /**
     * {@inheritdoc}
     * @see Scalr\Tests.WebTestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
    }

    /**
     * Makes a request to site
     *
     * @param   string    $uri         A request uri
     * @param   array      $parameters  optional Request parameters
     * @param   string     $method      optional HTTP Request method
     * @param   array      $server      optional Additional server options
     * @param   array      $files       optional Uploaded files array
     * @return  array|string            Returns array which represents returned json object or raw body content in the
     *                                  case if the responce is not a json.
     */
    protected function request($uri, array $parameters = array(), $method = 'GET', array $server = array(), array $files = array())
    {
        $aUri = parse_url($uri);
        $version = 'v1';
        call_user_func_array(array($this, 'getRequest'), array_merge(array(Scalr_UI_Request::REQUEST_TYPE_API, null), func_get_args()));
        Scalr_UI_Request::getInstance()->requestApiVersion = intval(trim($version, 'v'));

        $path = explode('/', trim($aUri['path'], '/'));
        Scalr_Api_Controller::handleRequest($path);
        $content = Scalr_UI_Response::getInstance()->getResponse();

        if ($this->apiDebug)
            var_dump($content);

        $arr = @json_decode($content, true);
        return $arr === null ? $content : $arr;
    }
}
