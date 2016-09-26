<?php

namespace Scalr\DependencyInjection;


/**
 * Rest Api sub container
 *
 * @author   Vitaliy Demidov    <vitaliy@scalr.com>
 * @since    5.4  (09.02.2015)
 *
 * @property \Scalr\Api\Rest\Routing\Router $router
 *           Gets REST API Router instance
 *
 * @property \Scalr\Api\Rest\Http\Request $request
 *           Gets REST API Http request instance
 *
 * @property \Scalr\Api\Rest\Environment $environment
 *           Gets REST API Environment variables
 *
 * @property \Scalr\Api\DataType\Meta $meta
 *           Gets Meta object which is the part of the API response
 *
 * @property \Scalr\Api\DataType\Warnings $warnings
 *           Gets Warnings object which is the part of the body API response
 *
 * @property array $settings
 *           API Application settings
 *
 * @method   \Scalr\Api\Rest\Controller\AbstractController controller()
 *           controller(string $controllerClass)
 *           Loads Controller of the specified class
 *
 */
class ApiContainer extends BaseContainer
{
    /**
     * Parent container
     *
     * @var Container
     */
    private $cont;

    /**
     * Sets main DI container
     *
     * @param   Container   $cont
     */
    public function setContainer(Container $cont)
    {
        $this->cont = $cont;
    }

    /**
     * Gets main DI container
     *
     * @return \Scalr\DependencyInjection\Container
     */
    public function getContainer()
    {
        return $this->cont;
    }
}