<?php

namespace Saxulum\RouteController\Annotation;

use Saxulum\RouteController\Annotation\Callback as CallbackAnnotation;
use Saxulum\RouteController\Helper\SetStateInterface;

/**
 * @Annotation
 * @Target({"CLASS","METHOD"})
 */
class Route implements SetStateInterface
{
    /**
     * @var string
     */
    protected $match;

    /**
     * @var string
     */
    protected $bind;

    /**
     * @var array
     */
    protected $asserts = array();

    /**
     * @var array
     */
    protected $values = array();

    /**
     * @var Convert[]
     */
    protected $converters = array();

    /**
     * @var string
     */
    protected $method;

    /**
     * @var boolean
     */
    protected $requireHttp = false;

    /**
     * @var boolean
     */
    protected $requireHttps = false;

    /**
     * @var CallbackAnnotation[]
     */
    protected $before = array();

    /**
     * @var CallbackAnnotation[]
     */
    protected $after = array();

    public function __construct(array $data)
    {
        if (isset($data['value'])) {
            $data['match'] = $data['value'];
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
     * @param $match
     */
    public function setMatch($match)
    {
        $this->match = $match;
    }

    /**
     * @return string
     */
    public function getMatch()
    {
        return $this->match;
    }

    /**
     * @param string $bind
     */
    public function setBind($bind)
    {
        $this->bind = $bind;
    }

    /**
     * @return string
     */
    public function getBind()
    {
        return $this->bind;
    }

    /**
     * @param array $asserts
     */
    public function setAsserts(array $asserts)
    {
        $this->asserts = $asserts;
    }

    /**
     * @return array
     */
    public function getAsserts()
    {
        return $this->asserts;
    }

    /**
     * @param array $values
     */
    public function setValues(array $values)
    {
        $this->values = $values;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * @param Convert[] $converters
     */
    public function setConverters(array $converters)
    {
        $this->converters = $converters;
    }

    /**
     * @return Convert[]
     */
    public function getConverters()
    {
        return $this->converters;
    }

    /**
     * @param string $method
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param boolean $requireHttp
     */
    public function setRequireHttp($requireHttp)
    {
        $this->requireHttp = $requireHttp;
    }

    /**
     * @return boolean
     */
    public function isRequireHttp()
    {
        return $this->requireHttp;
    }

    /**
     * @param boolean $requireHttps
     */
    public function setRequireHttps($requireHttps)
    {
        $this->requireHttps = $requireHttps;
    }

    /**
     * @return boolean
     */
    public function isRequireHttps()
    {
        return $this->requireHttps;
    }

    /**
     * @param CallbackAnnotation[] $before
     */
    public function setBefore(array $before)
    {
        $this->before = $before;
    }

    /**
     * @return CallbackAnnotation[]
     */
    public function getBefore()
    {
        return $this->before;
    }

    /**
     * @param CallbackAnnotation[] $after
     */
    public function setAfter(array $after)
    {
        $this->after = $after;
    }

    /**
     * @return CallbackAnnotation[]
     */
    public function getAfter()
    {
        return $this->after;
    }
}
