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
    protected $serviceId;

    /**
     * @var AnnotationInfo
     */
    protected $annotationInfo;

    /**
     * @var MethodInfo[]
     */
    protected $methodInfos;

    public function __construct($namespace, $serviceId, AnnotationInfo $annotationInfo, array $methodInfos = array())
    {
        $this->namespace = $namespace;
        $this->serviceId = $serviceId;
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
    public function getserviceId()
    {
        return $this->serviceId;
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
