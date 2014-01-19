<?php

namespace Saxulum\RouteController\Helper;

class ControllerInfo
{
    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var string
     */
    protected $serviceKey;

    /**
     * @var AnnotationInfo
     */
    protected $annotationInfo;

    /**
     * @var MethodInfo[]
     */
    protected $methodInfos;

    public function __construct($namespace, $serviceKey, AnnotationInfo $annotationInfo, array $methodInfos = array())
    {
        $this->namespace = $namespace;
        $this->serviceKey = $serviceKey;
        $this->annotationInfo = $annotationInfo;
        $this->methodInfos = $methodInfos;
    }

    /**
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @return string
     */
    public function getServiceKey()
    {
        return $this->serviceKey;
    }

    /**
     * @return AnnotationInfo
     */
    public function getAnnotationInfo()
    {
        return $this->annotationInfo;
    }

    /**
     * @return MethodInfo[]
     */
    public function getMethodInfos()
    {
        return $this->methodInfos;
    }
}
