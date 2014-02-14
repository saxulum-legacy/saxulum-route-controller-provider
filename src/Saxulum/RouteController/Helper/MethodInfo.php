<?php

namespace Saxulum\RouteController\Helper;

class MethodInfo implements SetStateInterface
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
     * @param AnnotationInfo $annotationInfo
     */
    public function __construct($name, AnnotationInfo $annotationInfo)
    {
        $this->name = $name;
        $this->annotationInfo = $annotationInfo;
    }

    /**
     * @param  array $array
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
