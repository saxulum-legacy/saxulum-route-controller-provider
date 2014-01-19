<?php

namespace Saxulum\RouteController\Annotation;

/**
 * @Annotation
 * @Target("ANNOTATION")
 */
class Callback
{
    /**
     * @var mixed $callback
     */
    protected $callback;

    public function __construct(array $data)
    {
        if (isset($data['value'])) {
            $data['callback'] = $data['value'];
            unset($data['value']);
        }

        foreach ($data as $key => $value) {
            $method = 'set'.str_replace('_', '', $key);
            if (!method_exists($this, $method)) {
                throw new \BadMethodCallException(sprintf("Unknown property '%s' on annotation '%s'.", $key, get_class($this)));
            }
            $this->$method($value);
        }
    }

    /**
     * @param mixed $callback
     */
    public function setCallback($callback)
    {
        $this->callback = $callback;
    }

    /**
     * @return mixed
     */
    public function getCallback()
    {
        return $this->callback;
    }
}
