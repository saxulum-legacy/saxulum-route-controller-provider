<?php

namespace Saxulum\RouteController\Annotation;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
class DI
{
    /**
     * @var bool
     */
    protected $injectContainer = false;

    /**
     * @var array
     */
    protected $serviceIds = array();

    public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            $method = 'set'.str_replace('_', '', $key);
            if (!method_exists($this, $method)) {
                throw new \BadMethodCallException(sprintf("Unknown property '%s' on annotation '%s'.", $key, get_class($this)));
            }
            $this->$method($value);
        }
    }

    /**
     * @param boolean $injectContainer
     */
    public function setInjectContainer($injectContainer)
    {
        $this->injectContainer = $injectContainer;
    }

    /**
     * @return boolean
     */
    public function isInjectContainer()
    {
        return $this->injectContainer;
    }

    /**
     * @param array $serviceIds
     */
    public function setServiceIds(array $serviceIds)
    {
        $this->serviceIds = $serviceIds;
    }

    /**
     * @return array
     */
    public function getServiceIds()
    {
        return $this->serviceIds;
    }
}