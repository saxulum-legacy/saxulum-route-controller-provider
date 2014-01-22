<?php

namespace Saxulum\RouteController\Helper;

use Saxulum\RouteController\Annotation\DI;
use Saxulum\RouteController\Annotation\Route;

class AnnotationInfo implements SetStateInterface
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
     * @param array $array
     * @return $this
     */
    public static function __set_state(array $array)
    {
        $reflectionClass = new \ReflectionClass(__CLASS__);

        return $reflectionClass->newInstanceArgs($array);
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
