<?php

namespace Saxulum\RouteController\Helper;

class ControllerInfo implements SetStateInterface
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
     * @param array $array
     * @return $this
     */
    public static function __set_state(array $array)
    {
        $reflectionClass = new \ReflectionClass(__CLASS__);

        return $reflectionClass->newInstanceArgs($array);
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

    /**
     * @param MethodInfo $methodInfo
     * @return $this
     */
    public function addMethodInfo(MethodInfo $methodInfo)
    {
        if (!in_array($methodInfo, $this->methodInfos)) {
            $this->methodInfos[] = $methodInfo;
        }

        return $this;
    }
}
