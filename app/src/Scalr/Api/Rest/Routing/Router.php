<?php

namespace Scalr\Api\Rest\Routing;

/**
 * Router
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (09.02.2015)
 */
class Router
{

    /**
     * Routing table
     *
     * @var \Scalr\Api\Rest\Routing\Table
     */
    private $routingTable;

    /**
     * Route groups
     *
     * @var array
     */
    private $routeGroups = [];

    /**
     * Processed group patterns
     *
     * @var array
     */
    private $processed = ['', []];

    /**
     * Matched routes
     *
     * @var array
     */
    private $matchedRoutes;

    /**
     * The list of the named routes
     *
     * @var array
     */
    private $namedRoutes = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->routeGroups = [];
        $this->routingTable = new Table();
    }

    /**
     * Pushes route group
     *
     * @param    string     $group      The group pattern
     * @param    array      $middleware optional The middleware
     * @return   number     Returns the index of the new group
     */
    public function pushGroup($group, $middleware = [])
    {
        $number = array_push($this->routeGroups, [$group => $middleware]);

        $this->processed = $this->processGroups();

        return $number;
    }

    /**
     * Retmoves the last group pattern from the stack
     *
     * @return boolean  Returns TRUE on success for FALSE otherwise
     */
    public function popGroup()
    {
        $group = array_pop($this->routeGroups);

        $this->processed = $this->processGroups();

        return $group !== null;
    }

    /**
     * Maps the route into routing table
     *
     * @param    Route   $route  A route instance
     * @return   Router  Returns current route instance
     */
    public function map(Route $route)
    {
        list($path, $groupMiddleware) = $this->processed;

        if ($path != '') {
            $route->setPath($path . $route->getPath());
        }

        if (!empty($groupMiddleware)) {
            $route->addMiddleware($groupMiddleware);
        }

        //Appends route to routing table.
        //We are using Trie search data structure.
        $this->routingTable->appendRoute($route);

        if ($route->getName() !== null) {
            //This is the named route
            if (isset($this->namedRoutes[$route->getName()])) {
                throw new \RuntimeException(sprintf("The Route with the name '%s' alredy exists", $route->getName()));
            } else {
                $this->namedRoutes[$route->getName()] = $route;
            }
        }

        return $this;
    }

    /**
     * Gets all matched routes
     *
     * @param    string       $method     The HTTP method
     * @param    string       $uri        Resource uri
     * @return   array[\Scalr\Api\Rest\Routing\Route] Returns array of the matched routes
     */
    public function getMatchedRoutes($method, $uri)
    {
        if ($this->matchedRoutes === null) {
            $this->matchedRoutes = $this->routingTable->getMatchedRoutes($method, $uri);
        }

        return $this->matchedRoutes;
    }

    /**
     * Processes groups
     *
     * @return array Returns an array with the pattern
     */
    protected function processGroups()
    {
        $pattern = "";
        $middleware = [];
        foreach ($this->routeGroups as $group) {
            $k = key($group);
            $pattern .= $k;

            if (is_array($group[$k])) {
                $middleware = array_merge($middleware, $group[$k]);
            }
        }

        return [$pattern, $middleware];
    }

    /**
     * Gets the list of the named routes
     *
     * @return   array    Returns the list of the named routes
     */
    public function getNamedRoutes()
    {
        return $this->namedRoutes;
    }

    /**
     * Gets URL path for the specified named route
     *
     * @param    string   $routeName  The name of the route
     * @param    array    $params     optional The parameters
     * @return   string   Returns URL path for the specified route
     */
    public function getPath($routeName, $params = [])
    {
        if (!isset($this->namedRoutes[$routeName])) {
            throw new \RuntimeException(sprintf("Unable to find named route %s", strip_tags($routeName)));
        }

        $search = [];

        foreach ($params as $key => $value) {
            $search[] = '~:' . preg_quote($key, '~') . '\+?(?!\w)~';
        }

        return preg_replace('~\(/?:.+\)|\(|\)|\\\\~', '', preg_replace($search, $params, $this->namedRoutes[$routeName]->getPath()));
    }
}