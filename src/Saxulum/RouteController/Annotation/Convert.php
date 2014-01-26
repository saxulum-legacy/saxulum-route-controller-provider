<?php

namespace Saxulum\RouteController\Annotation;

use Saxulum\RouteController\Annotation\Callback as CallbackAnnotation;
use Saxulum\RouteController\Helper\SetStateInterface;

/**
 * @Annotation
 * @Target("ANNOTATION")
 */
class Convert implements SetStateInterface
{
    /**
     * @var string
     */
    protected $variable;

    /**
     * @var CallbackAnnotation $callback
     */
    protected $callback;

    public function __construct(array $data)
    {
        if (isset($data['value'])) {
            $data['variable'] = $data['value'];
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
     * @param array $array
     * @return $this
     */
    public static function __set_state(array $array)
    {
        $reflectionClass = new \ReflectionClass(__CLASS__);

        return $reflectionClass->newInstance($array);
    }

    /**
     * @param string $variable
     */
    public function setVariable($variable)
    {
        $this->variable = $variable;
    }

    /**
     * @return string
     */
    public function getVariable()
    {
        return $this->variable;
    }

    /**
     * @param CallbackAnnotation $callback
     */
    public function setCallback($callback)
    {
        $this->callback = $callback;
    }

    /**
     * @return CallbackAnnotation
     */
    public function getCallback()
    {
        return $this->callback;
    }
}
