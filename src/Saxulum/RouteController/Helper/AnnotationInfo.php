<?php

namespace Saxulum\RouteController\Helper;

use Saxulum\RouteController\Annotation\Route;

class AnnotationInfo
{
    /**
     * @var Route
     */
    protected $route;

    /**
     * @param Route $route
     */
    public function __construct(Route $route = null)
    {
        $this->route = $route;
    }

    /**
     * @return Route
     */
    public function getRoute()
    {
        return $this->route;
    }
}
