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
     * @var array
     */
    protected $before = array();

    /**
     * @var array
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
     * @Target({"METHOD"})
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
     * @Target({"METHOD"})
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
     * @Target({"METHOD"})
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
     * @Target({"METHOD"})
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
     * @Target({"METHOD"})
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
     * @Target({"METHOD"})
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
     * @Target({"METHOD"})
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
     * @Target({"METHOD"})
     * @param array $before
     */
    public function setBefore(array $before)
    {
        $this->before = $before;
    }

    /**
     * @return array
     */
    public function getBefore()
    {
        return $this->before;
    }

    /**
     * @Target({"METHOD"})
     * @param array $after
     */
    public function setAfter(array $after)
    {
        $this->after = $after;
    }

    /**
     * @return array
     */
    public function getAfter()
    {
        return $this->after;
    }
}
