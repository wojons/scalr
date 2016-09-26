<?php

namespace Scalr\Api\Rest\Routing;

/**
 * Routing Item
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (13.02.2015)
 */
class Item implements \JsonSerializable
{
    /**
     * The part of the path
     *
     * @var PathPart
     */
    private $pathPart;

    /**
     * The list of the tables
     *
     * @var \Scalr\Api\Rest\Routing\Table
     */
    private $table;

    /**
     * The list of the matched routes
     *
     * @var array[\Scalr\Api\Rest\Routing\Route]
     */
    public $routes;

    /**
     * {@inheritdoc}
     * @see JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        $array = [
            'pathPart' => isset($this->pathPart) ? ['value' => $this->pathPart->value, 'type' => $this->pathPart->type] : null,
            'table'    => isset($this->table) ? $this->table->jsonSerialize() : null,
            'routes'   => []
        ];

        if (is_array($this->routes)) {
            foreach ($this->routes as $route) {
                $array['routes'][] = $route->getPath();
            }
        }

        return $array;
    }

    /**
     * Constructor
     *
     * @param   PathPart   $pathPart The part of the path
     */
    public function __construct(PathPart $pathPart)
    {
        $this->setPathPart($pathPart);
        $this->table = new Table();
        $this->routes = [];
    }

    /**
     * Sets the part of the path
     *
     * @param    PathPart $pathPart The part of the path
     * @return   Item
     */
    public function setPathPart(PathPart $pathPart)
    {
        $this->pathPart = $pathPart;

        return $this;
    }

    /**
     * Gets the part of the path
     *
     * @return \Scalr\Api\Rest\Routing\PathPart Returns the part of the path
     */
    public function getPathPart()
    {
        return $this->pathPart;
    }

    /**
     * Gets routing table that corresponds the part of the path
     *
     * @return \Scalr\Api\Rest\Routing\Table  Returns routing table
     */
    public function getTable()
    {
        return $this->table;
    }
}