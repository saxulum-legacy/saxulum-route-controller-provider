<?php

namespace Saxulum\RouteController\Annotation;

/**
 * @Annotation
 * @Target("ANNOTATION")
 */
class Callback
{
    /**
     * @var string $callback
     */
    public $value;
}
