<?php

namespace Saxulum\RouteController\Helper;

class MethodInfo
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var AnnotationInfo
     */
    protected $annotationInfo;

    /**
     * @param $name
     * @param RouteInfo $routeInfo
     */
    public function __construct($name, AnnotationInfo $annotationInfo)
    {
        $this->name = $name;
        $this->annotationInfo = $annotationInfo;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return AnnotationInfo
     */
    public function getAnnotationInfo()
    {
        return $this->annotationInfo;
    }
}
