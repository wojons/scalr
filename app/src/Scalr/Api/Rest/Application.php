<?php

namespace Scalr\Api\Rest;

use Scalr\Api\ApiLogger;
use Scalr\Api\Rest\Routing\Router;
use Scalr\Api\Rest\Routing\Route;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Rest\Http\Response;
use Scalr\Api\Rest\Exception\StopException;
use Scalr\Api\Rest\Environment as AppEnvironment;
use Scalr\Exception\LoggerException;

/**
 * REST API Framework
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (09.02.2015)
 *
 * @property   \Scalr\Api\Rest\Routing\Router  $router
 *             A router instance
 *
 * @property   \Scalr\Api\Rest\Http\Response $response
 *             The response instance
 *
 * @property   \Scalr\Api\Rest\Http\Request $request
 *             The request instance
 *
 * @property   \ArrayObject $settings
 *             API application settings
 */
class Application
{
    /**
     * Version of the API Framework
     */
    const VERSION = '0.1';

    /**
     * This settings is set by using :apiversion group parameter
     */
    const SETTING_API_VERSION = 'api.version';

    /**
     * It is can be used to mock environment
     */
    const SETTING_ENV_MOCK = 'environment.mock';

    /**
     * DI Container
     *
     * @var \Scalr\DependencyInjection\Container
     */
    private $container;

    /**
     * Error handler
     *
     * @var callable
     */
    private $error;

    /**
     * API logger
     *
     * @var ApiLogger
     */
    protected $apiLogger;

    /**
     * Preprocess request method and path
     *
     * @var callable
     */
    protected $pathPreprocessor;

    /**
     * API Application start time
     *
     * @var float
     */
    public $startTime;

    public static function getDefaultSettings()
    {
        return [
            self::SETTING_API_VERSION => null,
        ];
    }

    /**
     * Constructor
     *
     * @param   array   $settings   optional Application settings
     */
    public function __construct(array $settings = [])
    {
        $this->startTime = microtime(true);

        $this->container = \Scalr::getContainer();

        $this->container->api->settings = new \ArrayObject(array_merge(static::getDefaultSettings(), $settings));

        /* @var $apiContainer \Scalr\DependencyInjection\ApiContainer */
        $this->container->api->setShared('environment', function($apiContainer) {
            return new AppEnvironment(!empty($apiContainer->settings['environment.mock']) ? $apiContainer->settings['environment.mock'] : null);
        });

        $this->container->api->setShared('router', function($apiContainer) {
            return new Router();
        });

        $this->container->api->setShared('request', function($apiContainer) {
            return new Request($apiContainer->environment);
        });

        $this->container->api->setShared('response', function($apiContainer) {
            return new Response('', 200, ["content-type" => "application/json; charset=utf-8"]);
        });

        $this->container->api->set('controller', function($apiContainer, array $params = null) {
            $serviceid = 'controller' . (!empty($params[0]) ? '.' . str_replace('\\', '_', ltrim($params[0], '\\')) : '');

            if (!$apiContainer->initialized($serviceid)) {
                $apiContainer->setShared($serviceid, function($apiContainer) use ($params) {
                    $controllerClass = $params[0];

                    $abstractController = 'Scalr\\Api\\Rest\\Controller\\AbstractController';

                    if (!is_readable(SRCPATH . '/' . str_replace('\\', '/', $controllerClass) . '.php')) {
                        $this->notFound();
                    }

                    if (!class_exists($controllerClass) || !is_subclass_of($controllerClass, $abstractController)) {
                        throw new \InvalidArgumentException(sprintf(
                            "Controller must be subclass of the '%s' class. '%s' is given.",
                            $abstractController, $controllerClass
                        ));
                    }

                    /* @var $controller \Scalr\Api\Rest\Controller\AbstractController */
                    $controller = new $controllerClass;
                    $controller->setContainer($apiContainer->getContainer());
                    $controller->setApplication($this);

                    return $controller;
                });
            }

            return $apiContainer->get($serviceid);
        });

        try {
            $this->apiLogger = new ApiLogger(\Scalr::getContainer()->config->{'scalr.system.api.logger'});
        } catch (LoggerException $e) {
            \Scalr::getContainer()->logger(__CLASS__)->error("Wrong API Logger configuration: " . $e->getMessage());
        }

    }

    public function __get($name)
    {
        return $this->container->api->get($name);
    }

    public function __isset($name)
    {
        return $this->container->api->initialized($name);
    }

    /**
     * Adds a route to routing table
     *
     * @param   string         $path         The path pattern of the route
     * @param   array|callable $options      The options
     * @param   array          $requirements optional The requirements
     * @return  Route          Returns route instance
     */
    public function addRoute($path, $options, $requirements = [])
    {
        $route = new Route($path, $options, $requirements);

        $this->router->map($route);

        return $route;
    }

    /**
     * Adds get route
     *
     * @param   string         $path         The path pattern of the route
     * @param   array|callable $options      The options
     * @param   array          $requirements optional The requirements
     * @return  Route          Returns route instance
     */
    public function get($path, $options, $requirements = [])
    {
        return $this->addRoute($path, $options, $requirements)->setMethods([Request::METHOD_GET, Request::METHOD_HEAD]);
    }

    /**
     * Adds post route
     *
     * @param   string         $path         The path pattern of the route
     * @param   array|callable $options      The options
     * @param   array          $requirements optional The requirements
     * @return  Route          Returns route instance
     */
    public function post($path, $options, $requirements = [])
    {
        return $this->addRoute($path, $options, $requirements)->setMethods([Request::METHOD_POST]);
    }

    /**
     * Adds put route
     *
     * @param   string         $path         The path pattern of the route
     * @param   array|callable $options      The options
     * @param   array          $requirements optional The requirements
     * @return  Route          Returns route instance
     */
    public function put($path, $options, $requirements = [])
    {
        return $this->addRoute($path, $options, $requirements)->setMethods([Request::METHOD_PUT]);
    }

    /**
     * Adds patch route
     *
     * @param   string         $path         The path pattern of the route
     * @param   array|callable $options      The options
     * @param   array          $requirements optional The requirements
     * @return  Route          Returns route instance
     */
    public function patch($path, $options, $requirements = [])
    {
        return $this->addRoute($path, $options, $requirements)->setMethods([Request::METHOD_PATCH]);
    }

    /**
     * Adds delete route
     *
     * @param   string         $path         The path pattern of the route
     * @param   array|callable $options      The options
     * @param   array          $requirements optional The requirements
     * @return  Route          Returns route instance
     */
    public function delete($path, $options, $requirements = [])
    {
        return $this->addRoute($path, $options, $requirements)->setMethods([Request::METHOD_DELETE]);
    }

    /**
     * Adds options route
     *
     * @param   string         $path         The path pattern of the route
     * @param   array|callable $options      The options
     * @param   array          $requirements optional The requirements
     * @return  Route          Returns route instance
     */
    public function options($path, $options, $requirements = [])
    {
        return $this->addRoute($path, $options, $requirements)->setMethods([Request::METHOD_OPTIONS]);
    }

    /**
     * Route Groups
     */
    public function group()
    {
        $args = func_get_args();
        $path = array_shift($args);
        $callable = array_pop($args);

        $this->router->pushGroup($path, $args);

        if (is_callable($callable)) {
            call_user_func($callable);
        }

        $this->router->popGroup();
    }

    /**
     * Runs application
     */
    public function run()
    {
        $this->response->setHeader('X-Scalr-Inittime', microtime(true) - $this->startTime);

        $this->call();

        $this->response->setHeader('X-Scalr-Actiontime', microtime(true) - $this->startTime);

        //Fetch status, header, and body
        list($status, $headers, $body) = $this->response->finalize();

        if (headers_sent() === false) {
            if (strpos(PHP_SAPI, 'cgi') === 0) {
                header(sprintf('Status: %s', Response::getCodeMessage($status)));
            } else {
                header(sprintf('HTTP/%s %s', '1.1', Response::getCodeMessage($status)));
            }

            @header_remove('X-Powered-By');

            if (isset($this->settings[static::SETTING_API_VERSION])) {
                $headers['X-Powered-By'] = sprintf("Scalr API/%s", $this->settings[static::SETTING_API_VERSION]);
            }

            //Send headers
            foreach ($headers as $name => $value) {
                //Normalizes the header name
                $name = implode('-', array_map(function ($v) {
                    return ucfirst(strtolower($v));
                }, preg_split('/[_-]/', $name)));

                $a = explode("\n", $value);

                if ($a) {
                    foreach ($a as $v) {
                        header("$name: $v", false);
                    }
                }
            }
        }

        if ($this->request->getMethod() !== Request::METHOD_HEAD) {
            echo $body;
        }
    }

    /**
     * Error handler
     *
     * @param    int    $errno
     * @param    string $errstr
     * @param    string $errfile
     * @param    int    $errline
     * @return   boolean
     * @throws   \ErrorException
     */
    public static function handleErrors($errno, $errstr, $errfile, $errline)
    {
        // Handles error suppression.
        if (0 === error_reporting()) {
            return false;
        }

        throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
    }

    /**
     * Application wide handler
     */
    public function call()
    {
        $dispatched = null;
        try {
            ob_start();
            $matchedRoutes = $this->router->getMatchedRoutes($this->request->getMethod(), $this->request->getPathInfo(), $this->pathPreprocessor);
            foreach ($matchedRoutes as $route) {
                /* @var $route Route */
                $dispatched = $route->dispatch();
                if ($dispatched) {
                    break;
                }
            }

            if (!$dispatched) {
                $this->notFound();
            }

            $this->stop();
        } catch (StopException $e) {
            ob_end_clean();
        } catch (\Exception $e) {
            ob_end_clean();
            try {
                $this->error($e);
            } catch (StopException $e) {
            }
        }
    }


    /**
     * Stops application
     *
     * @throws   StopException
     */
    public function stop()
    {
        throw new StopException();
    }

    /**
     * Gets API Container
     *
     * @return \Scalr\DependencyInjection\Container Returns DI Container instance
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Not found handler
     */
    public function notFound()
    {
        $this->halt(404);
    }

    /**
     * Error handler
     *
     * It registers error handler or invokes registered error handler with an exception
     *
     * @param  \Callable|\Exception $e optional Either the callable handler or exception
     */
    public function error($e = null)
    {
        if (is_callable($e)) {
            $this->error = $e;
        } else {
            $this->response->setStatus(500);
            $this->response->setBody($this->callErrorHandler($e));

            $this->apiLogger->logError($this->request, $this->response);

            $this->stop();
        }
    }

    /**
     * Calls error handler
     *
     * @param    \Exception   $e optional An exception
     * @return string
     */
    protected function callErrorHandler($e = null)
    {
        return call_user_func_array((is_callable($this->error) ? $this->error : [$this, 'defaultError']), [$e]);
    }

    /**
     * Stops application immediately
     *
     * @param   number    $status   HTTP response status code
     * @param   string    $message  optional The response message
     */
    public function halt($status, $message = '')
    {
        $this->response->setStatus($status);
        $this->response->setBody($message);
        $this->stop();
    }

    /**
     * ApiVersion middleware handler
     *
     * It extracts :apiversion group parameter from the route
     * and sets application setting
     *
     * @param   Route   $route  A route
     */
    public function handleApiVersion($route)
    {
        $params = $route->getParams();

        if (!is_numeric($params['apiversion'])) {
            $this->halt(400, 'Invalid API version');
        }

        $this->settings[self::SETTING_API_VERSION] = (int) $params['apiversion'];

        unset($params['apiversion']);

        $route->setParams($params);
    }

    /**
     * Parses default template and returns its content
     *
     * @param    string     $title  A title
     * @param    string     $body   A body
     * @return   string     Returns parsed template
     */
    protected function getDefaultTemplate($title, $body)
    {
        return sprintf('<html><head><title>%s</title></head><body><h1>%s</h1>%s</body></html>', $title, $title, $body);
    }

    /**
     * Gets default error content
     *
     * @param     \ErrorException   $e optional An Exception
     * @return    string
     */
    protected function defaultError($e = null)
    {
        if ($e instanceof \Exception && !($e instanceof \ErrorException)) {
            \Scalr::logException($e);
        }
        return $this->getDefaultTemplate('Error', 'A webstite error has occured');
    }

    /**
     * Redirects to the specified url
     *
     * @param    string    $url     The URL
     * @param    number    $status  optional The HTTP response status code
     */
    public function redirect($url, $status = 302)
    {
        $this->response->redirect($url, $status);
        $this->halt($status);
    }

    /**
     * Redirects to the specified named route
     *
     * @param    string    $route  The name of the Route
     * @param    array     $params optional The list of the parameters
     * @param    number    $status optional The HTTP response status code.
     */
    public function redirectTo($route, $params = array(), $status = 302)
    {
        $this->redirect($this->getRouteUrl($route, $params), $status);
    }

    /**
     * Gets url for the specified route
     *
     * @param   string   $route  The name of the route
     * @param   array    $params optional The parameters
     * @return  string   Returns the URL for the specified Route
     */
    public function getRouteUrl($route, $params = [])
    {
        return $this->request->getUrl() . $this->router->getPath($route, $params);
    }
}