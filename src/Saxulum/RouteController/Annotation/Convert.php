<?php

namespace Saxulum\RouteController\Annotation;

use Saxulum\RouteController\Annotation\Callback as CallbackAnnotation;

/**
 * @Annotation
 * @Target("ANNOTATION")
 */
class Convert
{
    /**
     * @var string
     */
    public $variable;

    /**
     * @var CallbackAnnotation $callback
     */
    public $callback;

    public function __construct(array $data)
    {
        if (isset($data['value'])) {
            $data['variable'] = $data['value'];
            unset($data['value']);
        }

        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
}
