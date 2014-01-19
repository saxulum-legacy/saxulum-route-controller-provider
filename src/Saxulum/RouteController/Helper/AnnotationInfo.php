<?php

namespace Saxulum\RouteController\Helper;

use Saxulum\RouteController\Annotation\DI;
use Saxulum\RouteController\Annotation\Route;

class AnnotationInfo
{
    /**
     * @var Route
     */
    protected $route;

    /**
     * @var DI
     */
    protected $di;

    /**
     * @param Route $route
     */
    public function __construct(Route $route = null, DI $di = null)
    {
        $this->route = $route;
        $this->di = $di;
    }

    /**
     * @return Route
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * @return DI
     */
    public function getDi()
    {
        return $this->di;
    }
}
