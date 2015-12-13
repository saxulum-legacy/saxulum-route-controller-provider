<?php

namespace Saxulum\RouteController\Annotation;

/**
 * @Annotation
 * @Target({"CLASS","METHOD"})
 */
class DI
{
    /**
     * @var bool
     */
    public $injectContainer = false;

    /**
     * @var array
     */
    public $serviceIds = array();
}
