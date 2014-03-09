<?php

namespace Saxulum\RouteController\Helper;

abstract class AbstractInfo
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $annotations;

    public function __construct($name, array $annotations = array())
    {
        $this->name = $name;
        $this->annotations = $annotations;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getAnnotations()
    {
        return $this->annotations;
    }

    /**
     * @param  string $className
     * @return array
     */
    public function getAnnotationsInstanceof($className)
    {
        $annotationsInstanceOf = array();
        foreach ($this->getAnnotations() as $annotation) {
            if ($annotation instanceof $className) {
                $annotationsInstanceOf[] = $annotation;
            }
        }

        return $annotationsInstanceOf;
    }

    /**
     * @param  string      $className
     * @return null|object
     */
    public function getFirstAnnotationInstanceof($className)
    {
        foreach ($this->getAnnotations() as $annotation) {
            if ($annotation instanceof $className) {
                return $annotation;
            }
        }

        return null;
    }
}
