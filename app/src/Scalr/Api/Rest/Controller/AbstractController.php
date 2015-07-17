<?php

namespace Scalr\Api\Rest\Controller;

use Scalr\DependencyInjection\Container;
use Scalr\Api\Rest\Application;

/**
 * Abstract REST API Controller
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (10.02.2015)
 *
 * @property   \Scalr\Api\Rest\Routing\Router $router
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
abstract class AbstractController
{

    /**
     * DI Container instance
     *
     * @var  \Scalr\DependencyInjection\Container
     */
    protected $container;

    /**
     * Application instance
     *
     * @var  \Scalr\Api\Rest\Application
     */
    protected $app;

    /**
     * Sets DI Container
     *
     * @param  Container $container
     * @return AbstractController  Returns current instance
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Gets DI Container
     *
     * @return \Scalr\DependencyInjection\Container  Returns DI container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Sets REST API application
     *
     * @param    Application    $app  Application instance
     * @return   AbstractController
     */
    public function setApplication($app)
    {
        if (!($app instanceof Application)) {
            throw new \InvalidArgumentException(sprintf("Argument must be Scalr\\Api\\Rest\\Application instance."));
        }

        $this->app = $app;

        return $this;
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
     * Gets Application Instance
     *
     * @return   \Scalr\Api\Rest\Application  Returns application instance
     */
    public function getApplication()
    {
        return $this->app;
    }

}