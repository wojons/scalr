<?php

namespace Scalr\Api\Rest\Routing;

/**
 * Routing Table
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (13.02.2015)
 */
class Table implements \ArrayAccess, \IteratorAggregate, \Countable, \JsonSerializable
{
    /**
     * The list of the routing items
     * @var array[\Scalr\Api\Rest\Routing\Item]
     */
    private $array = [];

    /**
     * This flag is 0 while there is any regexp part in the table
     *
     * @var int
     */
    private $hasRegexp = 0;

    /**
     * Note: It is used only for debugging purposes and does not contain full version of
     * the child objects
     *
     * {@inheritdoc}
     * @see JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        $array = ['array' => [], 'hasRegexp' => $this->hasRegexp];

        foreach ($this->array as $routingItem) {
            $array['array'][] = $routingItem->jsonSerialize();
        }

        return $array;
    }

    /**
     * {@inheritdoc}
     * @see Countable::count()
     */
    public function count()
    {
        return count($this->array);
    }

    /**
     * {@inheritdoc}
     * @see IteratorAggregate::getIterator()
     * @return  \Scalr\Api\Rest\Routing\Item[]
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->array);
    }

    /**
     * Gets iterator for the regexp parts only
     *
     * @return \IteratorIterator Returns iterator for the regexp parts
     */
    public function getRegexpItemIterator()
    {
        return $this->hasRegexp ? new RegexpItemIterator($this->getIterator()) :
               new \ArrayIterator([]);
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetExists()
     */
    public function offsetExists($offset)
    {
        return isset($this->array[$offset]);
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetGet()
     * @return \Scalr\Api\Rest\Routing\Item
     */
    public function offsetGet($offset)
    {
        return $this->array[$offset];
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetSet()
     *
     * @param   string  $offset The part of the path
     * @param   Item    $value  The routing table item
     */
    public function offsetSet($offset, $value)
    {
        if ($value === null) {
            if ($this->offsetExists($offset)) {
                $this->offsetUnset($offset);
            }
        } elseif (!($value instanceof \Scalr\Api\Rest\Routing\Item)) {
            throw new \InvalidArgumentException(
                "Object of the Scalr\\Api\\Rest\\Routing\\Item class is expected for the value."
            );
        } else {
            if (!$value->getPathPart()->isString()) {
                $this->hasRegexp++;
            }

            $this->array[$offset] = $value;
        }
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetUnset()
     */
    public function offsetUnset($offset)
    {
        if (!$this->array[$offset]->getPathPart()->isString()) {
            $this->hasRegexp--;
        }

        unset($this->array[$offset]);
    }

    /**
     * Checks whether the table has regexp part of the path
     *
     * @return number  Returns the number of the regexp parts in the table
     */
    public function hasRegexp()
    {
        return $this->hasRegexp;
    }

    /**
     * Appends route to routing table
     *
     * @param    Route   $route     The route object
     * @param    array   $parts     optional The list of the parts in the route path
     * @return   Item    Returns the terminal item which contains the route
     */
    public function appendRoute(Route $route, array &$parts = null)
    {
        if ($parts === null) {
            $parts = explode('/', trim($route->getPath(), '/'));
        }

        $part = array_shift($parts);

        $pathPart = new PathPart($part);

        if (strpos($part, ':') !== false) {
            //This is regexp part
            $pathPart->type = PathPart::TYPE_REGEXP;
            $pathPart->value = '~^' . preg_replace_callback(
                '~:([\w]+)\+?~',
                function ($m) use ($route, $pathPart) {
                    $requirements = $route->getRequirements();

                    if (isset($requirements[$m[1]])) {
                        return '(?<' . $m[1] . '>' . $requirements[$m[1]] . ')';
                    }

                    if (substr($m[0], -1) === '+') {
                        $pathPart->type = PathPart::TYPE_REGEXP_PATH;
                        return '(?<' . $m[1] . '>.+)';
                    }

                    return '(?<' . $m[1] . '>[^/]+)';
                },
                str_replace(')', ')?', $part)
            ) . '$~';
        }

        if (!$this->offsetExists($pathPart->value)) {
            /* @var $item Item */
            $item = new Item($pathPart);
            $this[$pathPart->value] = $item;
        } else {
            $item = $this[$pathPart->value];
        }

        if (empty($parts)) {
            //This is the terminal item
            if (!in_array($route, $item->routes)) {
                $item->routes[] = $route;
            }

            return $item;
        }

        return $item->getTable()->appendRoute($route, $parts);
    }

    /**
     * Gets all matched routes
     *
     * @param    string        $method     The HTTP method
     * @param    string        $uri        The uri of the request
     * @param    array         $parts      optional Resource uri or array of the parts of the path
     * @return   array[\Scalr\Api\Rest\Routing\Route] Returns array of the matched routes
     */
    public function getMatchedRoutes($method, $uri, array &$parts = null)
    {
        $routes = [];

        if ($parts === null) {
            $uri = '/' . trim($uri, '/');
            $parts = explode('/', ltrim($uri, '/'));
        }

        $part = array_shift($parts);

        $matchRoutes = function(array $routes) use ($method, $uri) {
            return array_filter($routes, function (Route $route) use ($method, $uri) {
                return in_array($method, $route->getMethods(), true) && $route->matches($uri);
            });
        };

        //The part of the path matches
        if ($this->offsetExists($part)) {
            $item = $this[$part];

            //We can only rely on the string type of the part of the path here
            if ($item->getPathPart()->isString()) {
                //Whether this is a terminal item or not
                $routes = empty($parts) ?
                          //Time to return the result
                          (!empty($item->routes) ? $matchRoutes($item->routes) : []) :
                          //Proceeds with the tree
                          $item->getTable()->getMatchedRoutes($method, $uri, $parts);
            }
        }

        //We need also to run through all regexp parts of the path
        foreach ($this->getRegexpItemIterator() as $item) {
            if (preg_match($item->getPathPart()->value, $part, $matches)) {
                $routes = array_merge(
                    $routes,
                    (empty($parts) ? (!empty($item->routes) ? $matchRoutes($item->routes) : []) :
                                     $item->getTable()->getMatchedRoutes($method, $uri, $parts))
                );
            }
        }

        return $routes;
    }


}