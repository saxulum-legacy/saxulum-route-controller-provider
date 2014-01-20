<?php

namespace Saxulum\RouteController\Manager;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\Reader;
use Saxulum\RouteController\Annotation\DI;
use Saxulum\RouteController\Annotation\Route;
use Saxulum\RouteController\Helper\ClassFinder;
use Saxulum\RouteController\Helper\AnnotationInfo;
use Saxulum\RouteController\Helper\ControllerInfo;
use Saxulum\RouteController\Helper\MethodInfo;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class RouteControllerManager
{
    /**
     * @var array
     */
    protected $paths;

    /**
     * @var AnnotationReader
     */
    protected $annotationReader;

    public function __construct(
        array $paths = array(),
        Reader $annotationReader = null
    ) {
        $this->paths = $paths;
        $this->annotationReader = is_null($annotationReader) ? new AnnotationReader() : $annotationReader;
    }

    public function boot(Application $app)
    {
        foreach ($this->getControllerInfosForPaths() as $controllerInfo) {
            $app[$controllerInfo->getServiceKey()] = $app->share(function () use ($app, $controllerInfo) {
                $controllerReflectionClass = new \ReflectionClass($controllerInfo->getNamespace());
                $di = $controllerInfo->getAnnotationInfo()->getDI();
                if (!is_null($di)) {
                    if ($di->isInjectContainer()) {
                        $controller = $controllerReflectionClass->newInstanceArgs(array($app));
                    } else {
                        $args = array();
                        foreach ($di->getServiceIds() as $serviceId) {
                            $args[] = $app[$serviceId];
                        }
                        $controller = $controllerReflectionClass->newInstanceArgs($args);
                    }
                } else {
                    $controller = new $controllerInfo->getNamespace();
                }

                foreach ($controllerInfo->getMethodInfos() as $methodInfo) {
                    $di = $methodInfo->getAnnotationInfo()->getDI();
                    if (!is_null($di)) {
                        if ($di->isInjectContainer()) {
                            call_user_func(array($controller, $methodInfo->getName()), $app);
                        } else {
                            $args = array();
                            foreach ($di->getServiceIds() as $serviceId) {
                                $args[] = $app[$serviceId];
                            }
                            call_user_func_array(array($controller, $methodInfo->getName()), $args);
                        }
                    }
                }

                return $controller;
            });

            /** @var ControllerCollection $controllers */
            $controllers = $app['controllers_factory'];

            foreach ($controllerInfo->getMethodInfos() as $methodInfo) {
                $route = $methodInfo->getAnnotationInfo()->getRoute();
                if (!is_null($route)) {
                    $to = $controllerInfo->getServiceKey() . ':' . $methodInfo->getName();
                    $controller = $controllers->match($route->getMatch(), $to);
                    $controller->bind($route->getBind());
                    foreach ($route->getAsserts() as $variable => $regexp) {
                        $controller->assert($variable, $regexp);
                    }
                    foreach ($route->getValues() as $variable => $default) {
                        $controller->value($variable, $default);
                    }
                    foreach ($route->getConverters() as $converter) {
                        $callback = $converter->getCallback()->getCallback();
                        if (is_string($callback) && count($callbackParts = explode(':', $callback)) == 2) {
                            if ($callbackParts[0] == '__self') {
                                $callbackParts[0] = $controllerInfo->getServiceKey();
                            }
                            $controller->convert($converter->getVariable(), function ($variable) use ($app, $callbackParts) {
                                return $app[$callbackParts[0]]->$callbackParts[1]($variable);
                            });
                        } else {
                            $controller->convert($converter->getVariable(), $callback);
                        }
                    }
                    $controller->method($route->getMethod());
                    if ($route->isRequireHttp()) {
                        $controller->requireHttp();
                    }
                    if ($route->isRequireHttps()) {
                        $controller->requireHttps();
                    }
                    foreach ($route->getBefore() as $before) {
                        $callback = $before->getCallback();
                        if (is_string($callback) && count($callbackParts = explode(':', $callback)) == 2) {
                            if ($callbackParts[0] == '__self') {
                                $callbackParts[0] = $controllerInfo->getServiceKey();
                            }
                            $controller->before(function () use ($app, $callbackParts) {
                                return $app[$callbackParts[0]]->$callbackParts[1]();
                            });
                        } else {
                            $controller->before($callback);
                        }
                    }
                    foreach ($route->getAfter() as $after) {
                        $callback = $after->getCallback();
                        if (is_string($callback) && count($callbackParts = explode(':', $callback)) == 2) {
                            if ($callbackParts[0] == '__self') {
                                $callbackParts[0] = $controllerInfo->getServiceKey();
                            }
                            $controller->after(function () use ($app, $callbackParts) {
                                return $app[$callbackParts[0]]->$callbackParts[1]();
                            });
                        } else {
                            $controller->after($callback);
                        }
                    }
                }
            }

            $prefix = '';
            $route = $controllerInfo->getAnnotationInfo()->getRoute();
            if (!is_null($route)) {
                $prefix = $route->getMatch();
            }

            $app->mount($prefix, $controllers);
        }
    }

    /**
     * @return ControllerInfo[]
     */
    protected function getControllerInfosForPaths()
    {
        $controllerInfos = array();
        foreach ($this->paths as $path) {
            $controllerInfos = array_merge($controllerInfos, $this->getControllerInfos($path));
        }

        return $controllerInfos;
    }

    /**
     * @param $path
     * @return ControllerInfo[]
     */
    protected function getControllerInfos($path)
    {
        $controllerInfos = array();
        foreach ($this->getControllerReflections($path) as $controllerReflection) {
            $controllerInfos[] = $this->getControllerInfo($controllerReflection);
        }

        return $controllerInfos;
    }

    /**
     * @param  \ReflectionClass $reflectionClass
     * @return ControllerInfo
     */
    protected function getControllerInfo(\ReflectionClass $reflectionClass)
    {
        $methodInfos = array();

        $methodRouteAnnotations = array();
        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $methodInfo = $this->getMethodInfo($reflectionMethod);
            if (!is_null($methodInfo)) {
                $methodInfos[] = $methodInfo;
            }
        }

        return $this->getClassInfo($reflectionClass, $methodInfos);
    }

    /**
     * @param  \ReflectionMethod $reflectionMethod
     * @return MethodInfo
     */
    protected function getMethodInfo(\ReflectionMethod $reflectionMethod)
    {
        if ($reflectionMethod->isPublic()) {
            $methodAnnotations = $this
                ->annotationReader
                ->getMethodAnnotations($reflectionMethod)
            ;

            $routeAnnotation = null;
            $diAnnotation = null;

            foreach ($methodAnnotations as $methodAnnotation) {
                if ($methodAnnotation instanceof Route) {
                    $routeAnnotation = $methodAnnotation;
                } elseif ($methodAnnotation instanceof DI) {
                    $diAnnotation = $methodAnnotation;
                }
            }

            if (!is_null($routeAnnotation) || !is_null($diAnnotation)) {
                return new MethodInfo(
                    $reflectionMethod->getName(),
                    new AnnotationInfo($routeAnnotation, $diAnnotation)
                );
            }
        }

        return null;
    }

    /**
     * @param \ReflectionClass $reflectionClass
     * @param $methodInfos
     * @return ControllerInfo
     */
    protected function getClassInfo(\ReflectionClass $reflectionClass, $methodInfos)
    {
        $classAnnotation = $this
            ->annotationReader
            ->getClassAnnotations($reflectionClass)
        ;

        $routeAnnotation = null;
        $diAnnotation = null;

        foreach ($classAnnotation as $classAnnotation) {
            if ($classAnnotation instanceof Route) {
                $routeAnnotation = $classAnnotation;
            } elseif ($classAnnotation instanceof DI) {
                $diAnnotation = $classAnnotation;
            }
        }

        $classNamespace = $reflectionClass->getName();

        return new ControllerInfo(
            $classNamespace,
            $this->namespaceToServiceKey($classNamespace),
            new AnnotationInfo($routeAnnotation, $diAnnotation),
            $methodInfos
        );
    }

    /**
     * @param  string                    $path
     * @return \ReflectionClass[]
     * @throws \InvalidArgumentException
     */
    protected function getControllerReflections($path)
    {
        $controllerReflections = array();

        if (!is_dir($path)) {
            throw new \InvalidArgumentException('Path is not a directory');
        }

        foreach (Finder::create()->files()->name('*Controller.php')->in($path) as $file) {
            $controllerNamespaces = $this->findClassesWithinAFile($file);
            foreach ($controllerNamespaces as $controllerNamespace) {
                $reflectionClass = new \ReflectionClass($controllerNamespace);
                if ($reflectionClass->isInstantiable()) {
                    $controllerReflections[] = $reflectionClass;
                }
            }
        }

        return $controllerReflections;
    }

    /**
     * @param  SplFileInfo $file
     * @return array
     */
    protected function findClassesWithinAFile(SplFileInfo $file)
    {
        return ClassFinder::findClasses($file->getContents());
    }

    /**
     * @param  string $namespace
     * @return string
     */
    protected function namespaceToServiceKey($namespace)
    {
        return str_replace('\\', '.', strtolower($namespace));
    }
}
