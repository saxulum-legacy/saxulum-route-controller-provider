<?php

namespace Saxulum\RouteController\Annotation;

/**
 * @Annotation
 * @Target("ANNOTATION")
 */
class Convert
{
    /**
     * @var string
     */
    public $value;

    /**
     * @var Saxulum\RouteController\Annotation\Callback $callback
     */
    public $callback;
}
