<?php

namespace Saxulum\RouteController\Manager;

use Doctrine\Common\Annotations\Reader;
use Saxulum\ClassFinder\ClassFinder;
use Saxulum\RouteController\Helper\ClassInfo;
use Saxulum\RouteController\Helper\MethodInfo;
use Saxulum\RouteController\Helper\PropertyInfo;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class AnnotationManager
{
    /**
     * @var Reader
     */
    protected $annotationReader;

    public function __construct(Reader $annotationReader)
    {
        $this->annotationReader = $annotationReader;
    }

    /**
     * @param  array       $paths
     * @return ClassInfo[]
     */
    public function buildClassInfosBasedOnPaths(array $paths = array())
    {
        $classInfos = array();
        foreach ($paths as $path) {
            $classInfos = array_merge($classInfos, $this->buildClassInfosBasedOnPath($path));
        }

        return $classInfos;
    }

    /**
     * @param  string      $path
     * @return ClassInfo[]
     */
    public function buildClassInfosBasedOnPath($path)
    {
        return $this->buildClassInfos(self::getReflectionClasses($path));
    }

    /**
     * @param  \ReflectionClass[] $reflectionClasses
     * @return ClassInfo[]
     */
    public function buildClassInfos(array $reflectionClasses)
    {
        $classInfos = array();
        foreach ($reflectionClasses as $reflectionClass) {
            $classInfos[] = $this->buildClassInfo($reflectionClass);
        }

        return $classInfos;
    }

    /**
     * @param  \ReflectionClass $reflectionClass
     * @return ClassInfo
     */
    public function buildClassInfo(\ReflectionClass $reflectionClass)
    {
        $classAnnotations = $this
            ->annotationReader
            ->getClassAnnotations($reflectionClass)
        ;

        return new ClassInfo(
            $reflectionClass->getName(),
            $classAnnotations,
            $this->buildPropertyInfos($reflectionClass),
            $this->buildMethodInfos($reflectionClass)
        );
    }

    /**
     * @param  \ReflectionClass $reflectionClass
     * @return PropertyInfo[]
     */
    protected function buildPropertyInfos(\ReflectionClass $reflectionClass)
    {
        $propertyInfos = array();
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $propertyInfos[] = $this->buildPropertyInfo($reflectionProperty);
        }

        return $propertyInfos;
    }

    /**
     * @param  \ReflectionProperty $reflectionProperty
     * @return PropertyInfo
     */
    protected function buildPropertyInfo(\ReflectionProperty $reflectionProperty)
    {
        $propertyAnnotations = $this
            ->annotationReader
            ->getPropertyAnnotations($reflectionProperty)
        ;

        return new PropertyInfo(
            $reflectionProperty->getName(),
            $propertyAnnotations
        );
    }

    /**
     * @param  \ReflectionClass $reflectionClass
     * @return MethodInfo[]
     */
    protected function buildMethodInfos(\ReflectionClass $reflectionClass)
    {
        $methodInfos = array();
        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $methodInfos[] = $this->buildMethodInfo($reflectionMethod);
        }

        return $methodInfos;
    }

    /**
     * @param  \ReflectionMethod $reflectionMethod
     * @return MethodInfo
     */
    protected function buildMethodInfo(\ReflectionMethod $reflectionMethod)
    {
        $methodAnnotations = $this
            ->annotationReader
            ->getMethodAnnotations($reflectionMethod)
        ;

        return new MethodInfo(
            $reflectionMethod->getName(),
            $methodAnnotations
        );
    }

    /**
     * @param  string                    $path
     * @return \ReflectionClass[]
     * @throws \InvalidArgumentException
     */
    public static function getReflectionClasses($path)
    {
        $classReflections = array();

        if (!is_dir($path)) {
            throw new \InvalidArgumentException('Path is not a directory');
        }

        foreach (Finder::create()->files()->name('*.php')->in($path) as $file) {
            $namespaces = self::findClassesWithinAFile($file);
            foreach ($namespaces as $classNamespace) {
                $reflectionClass = new \ReflectionClass($classNamespace);
                if ($reflectionClass->isInstantiable()) {
                    $classReflections[] = $reflectionClass;
                }
            }
        }

        return $classReflections;
    }

    /**
     * @param  SplFileInfo $file
     * @return array
     */
    public static function findClassesWithinAFile(SplFileInfo $file)
    {
        return ClassFinder::findClasses($file->getContents());
    }
}
