<?php

namespace Saxulum\RouteController\Annotation;

/**
 * @Annotation
 * @Target("ANNOTATION")
 */
class Callback
{
    /**
     * @var callable $callback
     */
    public $callback;

    public function __construct(array $data)
    {
        if (isset($data['value'])) {
            $data['callback'] = $data['value'];
            unset($data['value']);
        }

        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
}
