<?php

namespace Scalr\Api\Rest\Routing;

use Scalr\Api\Rest\Http\Request;

/**
 * Route
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (09.02.2015)
 */
class Route
{

    /**
     * The path pattern
     *
     * @var string
     */
    private $path = '/';

    /**
     * Default settings
     *
     * @var array
     */
    private $defaults = [];

    /**
     * Requirements
     *
     * @var array
     */
    private $requirements = [];

    /**
     * The params values
     *
     * @var array
     */
    private $params;

    /**
     * The names of the parameters
     *
     * @var array
     */
    private $paramNames = [];

    private $paramNamesPath = [];

    /**
     * The name of the route
     *
     * @var string
     */
    private $name;

    /**
     * Constructor
     *
     * @param    string                $path         The path pattern
     * @param    array|callable|string $defaults     optional An array of the default parameters or
     *                                               Callable controller's handler or
     *                                               The compound name of the route which is also the handler
     *                                               <class_name>:<method>
     * @param    array                 $requirements optional The array of requirements (regexp patterns)
     */
    public function __construct($path, $defaults = [], array $requirements = [])
    {
        $this->setPath($path);
        $this->setDefaults($defaults);
        $this->setRequirements($requirements);
    }

    /**
     * Sets a path pattern
     *
     * @param    string     $path   The path pattern
     * @return   Route      The current route instance
     */
    public function setPath($path)
    {
        $this->path = '/' . ltrim(trim($path), '/');

        return $this;
    }

    /**
     * Gets a path pattern
     *
     * @return   string  Returns a path pattern
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Sets default parameters
     *
     * @param    array|callable $defaults  The default parameters
     * @return   Route
     */
    public function setDefaults($defaults)
    {
        $this->defaults = [];
        $this->defaults['middleware'] = [];
        $this->defaults['methods'] = [];

        return $this->addDefaults($defaults);
    }

    /**
     * Add default parameters
     *
     * @param    array|callable $defaults  The default parameters
     * @return   Route
     */
    public function addDefaults($defaults)
    {
        if (is_callable($defaults)) {
            $this->defaults['controller'] = $defaults;
        } else if (is_string($defaults)) {
            $this->defaults['controller'] = $defaults;
            //The string is considered to be the name of the route
            $this->setName($defaults);
        } else {
            if (isset($defaults['name'])) {
                $this->setName($defaults['name']);
                unset($defaults['name']);
            }

            foreach ($defaults as $key => $value) {
                $this->defaults[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Sets requirements
     *
     * IMPORTANT! This method should not be invoked with the route which has already
     * been mapped because the routing table should be re-maped.
     *
     * @param    array   $requirements  The requirements
     * @return   Route
     */
    public function setRequirements(array $requirements)
    {
        $this->requirements = [];

        return $this->addRequirements($requirements);
    }

    /**
     * Gets requirements array
     *
     * @return    array  Returns array of the requirements
     */
    public function getRequirements()
    {
        return $this->requirements;
    }

    /**
     * Add requirements
     *
     * IMPORTANT! This method should not be invoked with the route which has already
     * been mapped because the routing table should be re-maped
     *
     * @param    array   $requirements  The requirements
     * @return   Route   Returns current route instance
     */
    public function addRequirements(array $requirements)
    {
        foreach ($requirements as $key => $value) {
            $this->requirements[$key] = $value;
        }

        return $this;
    }

    /**
     * Gets default parameters
     *
     * @return   array  Returns default parameters
     */
    public function getDefaults()
    {
        return $this->defaults;
    }

    /**
     * Sets default parameter
     *
     * @param   string    $name    The name of the parameter
     * @param   mixed     $default The value of the parameter
     * @return  Route     Returns current route instance
     */
    public function setDefault($name, $default)
    {
        if ($name == 'name') {
            $this->name = $default;
        } else {
            $this->defaults[$name] = $default;
        }

        return $this;
    }

    /**
     * Sets route middleware
     *
     * @param   array|callable  $middleware  The middleware
     * @throws  \InvalidArgumentException
     * @return  Route  Returns current route instance
     */
    public function addMiddleware($middleware)
    {
        if (is_callable($middleware)) {
            $this->defaults['middleware'][] = $middleware;
        } elseif (is_array($middleware)) {
            foreach ($middleware as $callable) {
                if (!is_callable($callable)) {
                    throw new \InvalidArgumentException('All Route middleware must be callable');
                }
            }
            $this->defaults['middleware'] = array_merge($this->defaults['middleware'], $middleware);
        } else {
            throw new \InvalidArgumentException('Route middleware must be callable or an array of callables');
        }

        return $this;
    }

    /**
     * Sets route middleware
     *
     * @param   array[callable] $middleware
     * @return  Route  Returns current route instance
     */
    public function setMiddleware($middleware)
    {
        $this->defaults['middleware'] = [];

        return $this->addMiddleware($middleware);
    }

    /**
     * Gets route middleware
     *
     * @return   array[callable]  Returns route middleware
     */
    public function getMidleware()
    {
        return $this->defaults['middleware'];
    }

    /**
     * Sets allowed methods
     *
     * @param   array $methods  The methods which are allowed for the route
     * @return  Route Returns current route instance
     */
    public function setMethods(array $methods)
    {
        $this->defaults['methods'] = [];

        return $this->addMethods($methods);
    }

    /**
     * Adds allowed methods for the route
     *
     * @param   array   $methods  The methods which are allowed for the route
     * @return  Route   Returns current route instance
     */
    public function addMethods(array $methods)
    {
        foreach ($methods as $method) {
            $this->addMethod($method);
        }

        return $this;
    }

    /**
     * Add allowed method for the route
     *
     * @param    string     $method   The HTTP method
     * @return   Route      Returns current route instance
     */
    public function addMethod($method)
    {
        if (!Request::hasMethod($method)) {
            throw new \InvalidArgumentException(sprintf("HTTP method '%s' is not accepted for the Request.", $method));
        }

        $this->defaults['methods'] = array_merge($this->defaults['methods'], [$method]);

        return $this;
    }

    /**
     * Gets allowed methods for the route
     *
     * @return  array Returns allowed methods for the request
     */
    public function getMethods()
    {
        return $this->defaults['methods'];
    }

    /**
     * Checks whether specified HTTP method is supported by the route
     *
     * @return   bool  Returns TRUE if the specified method is supported by the route or
     *                 FALSE otherwise
     */
    public function hasMethod($method)
    {
        return in_array($method, $this->defaults['methods']);
    }

    /**
     * Checks whether the route matches uri
     *
     * @param    string   $uri URI of the request
     * @return   bool
     */
    public function matches($uri)
    {
        //Initializes arrays
        $this->paramNames = [];
        $this->paramNamesPath = [];
        $this->params = [];

        $preg = preg_replace_callback(
            '~:([\w]+)\+?~',
            [$this, 'matchesCallback'],
            str_replace(')', ')?', (string)$this->path)
        );

        if (substr($this->path, -1) === '/') {
            $preg .= '?';
        }

        $regex = '~^' . $preg . '$~';

        $values = [];

        if (!preg_match($regex, $uri, $values)) {
            return false;
        }

        //Sets the value for an each parameter
        foreach ($this->paramNames as $name) {
            if (isset($values[$name])) {
                if (isset($this->paramNamesPath[$name])) {
                    $this->params[$name] = explode('/', urldecode($values[$name]));
                } else {
                    $this->params[$name] = urldecode($values[$name]);
                }
            }
        }

        return true;
    }

    /**
     * Converts route parameter into regular expression
     *
     * @param    array   $m   parameter patterns
     * @return   string       Regular expression for the route url parameter
     */
    protected function matchesCallback($m)
    {
        $this->paramNames[] = $m[1];

        if (isset($this->requirements[$m[1]])) {
            return '(?<' . $m[1] . '>' . $this->requirements[$m[1]] . ')';
        }

        if (substr($m[0], -1) === '+') {
            $this->paramNamesPath[$m[1]] = 1;
            return '(?<' . $m[1] . '>.+)';
        }

        return '(?<' . $m[1] . '>[^/]+)';
    }

    /**
     * Gets route handler
     *
     * @return   callable   Returns controller's handler
     */
    public function getHandler()
    {
        if (is_callable($this->defaults['controller']))
            return $this->defaults['controller'];

        //Named route which is the compound handler "{class}:{method}"
        $arr = explode(':', $this->defaults['controller']);
        if (count($arr) == 2) {
            $controllerClass = $arr[0];
            $method = $arr[1];

            $controller = \Scalr::getContainer()->api->controller($controllerClass);

            if (!is_callable([$controller, $method])) {
                throw new \RuntimeException(sprintf("There is no method '%s' in the '%s' class.", $method, get_class($controller)));
            }
        } else {
            throw new \InvalidArgumentException(sprintf(
                "Invalid compound handler '%s'. It should be '{class}:{method}'", $this->defaults['controller']
            ));
        }

        return [$controller, $method];
    }

    /**
     * Gets params for the request
     *
     * @return array  Returns request's params
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Sets params values
     *
     * @param    array    $params  The params looks like array(paramName => value)
     * @return   Route
     */
    public function setParams($params)
    {
        $this->params = $params;

        return $this;
    }

    /**
     * Dispatch route
     *
     * @return bool
     */
    public function dispatch()
    {
        foreach ($this->defaults['middleware'] as $m) {
            call_user_func_array($m, [$this]);
        }

        $return = call_user_func_array($this->getHandler(), array_values($this->getParams()));

        return $return === false ? false : true;
    }

    /**
     * Gets the name of the route
     *
     * @return   string  The name of the route
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the name of the route
     *
     * @param   string     $name  The name of the Route
     * @return  \Scalr\Api\Rest\Routing\Route  The current instance
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }
}