<?php

namespace Saxulum\RouteController\Annotation;

use Saxulum\RouteController\Annotation\Callback as CallbackAnnotation;

/**
 * @Annotation
 * @Target({"CLASS","METHOD"})
 */
class Route
{
    /**
     * @var string
     */
    public $match;

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
     * @var Convert[]
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
     * @var CallbackAnnotation[]
     */
    public $before = array();

    /**
     * @var CallbackAnnotation[]
     */
    public $after = array();

    public function __construct(array $data)
    {
        if (isset($data['value'])) {
            $data['match'] = $data['value'];
            unset($data['value']);
        }

        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
}
