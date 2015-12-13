<?php

namespace Saxulum\RouteController\Annotation;

/**
 * @Annotation
 * @Target({"CLASS","METHOD"})
 */
class Route
{
    /**
     * @var string
     */
    public $value;

    /**
     * @var string
     */
    public $bind;

    /**
     * @var array
     */
    public $asserts = array();

    /**
     * @var array
     */
    public $values = array();

    /**
     * @var array
     */
    public $converters = array();

    /**
     * @var string
     */
    public $method;

    /**
     * @var boolean
     */
    public $requireHttp = false;

    /**
     * @var boolean
     */
    public $requireHttps = false;

    /**
     * @var array
     */
    public $before = array();

    /**
     * @var array
     */
    public $after = array();
}
