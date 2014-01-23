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

    /**
     * @param array  $paths
     * @param Reader $annotationReader
     */
    public function __construct(
        array $paths = array(),
        Reader $annotationReader = null,
        $cache = null
    ) {
        $this->paths = $paths;
        $this->annotationReader = is_null($annotationReader) ? new AnnotationReader() : $annotationReader;
        $this->cache = $cache;

        if (!is_null($this->cache) && !is_dir($this->cache)) {
            mkdir($this->cache, 0777, true);
        }
    }

    /**
     * @param Application $app
     */
    public function boot(Application $app)
    {
        if (!is_null($this->cache)) {
            $cacheFile = $this->cache . '/saxulum-route-controller.php';
            if ($app['debug'] || !file_exists($cacheFile)) {
                file_put_contents($cacheFile, '<?php return ' . var_export($this->getControllerInfos(), true) . ';');
            }
            $controllerInfosForPaths = require($cacheFile);
        } else {
            $controllerInfosForPaths = $this->getControllerInfos();
        }

        foreach ($controllerInfosForPaths as $controllerInfo) {
            if ($this->addRoutes($app, $controllerInfo)) {
                $this->addControllerService($app, $controllerInfo);
            }
        }
    }

    /**
     * @param  Application    $app
     * @param  ControllerInfo $controllerInfo
     * @return bool
     */
    protected function addRoutes(Application $app, ControllerInfo $controllerInfo)
    {
        /** @var ControllerCollection $controllers */
        $controllers = $app['controllers_factory'];
        $isController = false;
        foreach ($controllerInfo->getMethodInfos() as $methodInfo) {
            $route = $methodInfo->getAnnotationInfo()->getRoute();
            if (!is_null($route)) {
                $isController = true;
                $to = $controllerInfo->getserviceId() . ':' . $methodInfo->getName();
                $controller = $controllers->match($route->getMatch(), $to);
                $controller->bind($route->getBind());
                foreach ($route->getAsserts() as $variable => $regexp) {
                    $controller->assert($variable, $regexp);
                }
                foreach ($route->getValues() as $variable => $default) {
                    $controller->value($variable, $default);
                }
                foreach ($route->getConverters() as $converter) {
                    $controller->convert(
                        $converter->getVariable(),
                        $this->fixCallback(
                            $app,
                            $controllerInfo,
                            $converter->getCallback()->getCallback()
                        )
                    );
                }
                $controller->method($route->getMethod());
                if ($route->isRequireHttp()) {
                    $controller->requireHttp();
                }
                if ($route->isRequireHttps()) {
                    $controller->requireHttps();
                }
                foreach ($route->getBefore() as $before) {
                    $controller->before(
                        $this->fixCallback($app, $controllerInfo, $before->getCallback())
                    );
                }
                foreach ($route->getAfter() as $after) {
                    $controller->after(
                        $this->fixCallback($app, $controllerInfo, $after->getCallback())
                    );
                }
            }
        }
        if ($isController) {
            $prefix = '';
            $route = $controllerInfo->getAnnotationInfo()->getRoute();
            if (!is_null($route)) {
                $prefix = $route->getMatch();
            }

            $app->mount($prefix, $controllers);

            return true;
        }

        return false;
    }

    /**
     * @param Application    $app
     * @param ControllerInfo $controllerInfo
     */
    protected function addControllerService(Application $app, ControllerInfo $controllerInfo)
    {
        $app[$controllerInfo->getserviceId()] = $app->share(function () use ($app, $controllerInfo) {
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
                $controller = $controllerReflectionClass->newInstance();
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
    }

    /**
     * @return ControllerInfo[]
     */
    protected function getControllerInfos()
    {
        $controllerInfos = array();
        foreach ($this->paths as $path) {
            foreach ($this->getControllerReflections($path) as $controllerReflection) {
                $controllerInfo = $this->getControllerInfo($controllerReflection);
                if (!is_null($controllerInfo)) {
                    $controllerInfos[] = $this->getControllerInfo($controllerReflection);
                }
            }
        }

        return $controllerInfos;
    }

    /**
     * @param  \ReflectionClass    $reflectionClass
     * @return ControllerInfo|null
     */
    protected function getControllerInfo(\ReflectionClass $reflectionClass)
    {
        $gotRoute = false;
        $methodInfos = array();

        $methodRouteAnnotations = array();
        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $methodInfo = $this->getMethodInfo($reflectionMethod);
            if (!is_null($methodInfo)) {
                if (!is_null($methodInfo->getAnnotationInfo()->getRoute())) {
                    $gotRoute = true;
                }
                $methodInfos[] = $methodInfo;
            }
        }

        if (!$gotRoute) {
            return null;
        }

        return $this->getClassInfo($reflectionClass, $methodInfos);
    }

    /**
     * @param  \ReflectionClass $reflectionClass
     * @param  MethodInfo[]     $methodInfos
     * @return ControllerInfo
     */
    protected function getClassInfo(\ReflectionClass $reflectionClass, array $methodInfos)
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
            $this->namespaceToserviceId($classNamespace),
            new AnnotationInfo($routeAnnotation, $diAnnotation),
            $methodInfos
        );
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

        foreach (Finder::create()->files()->name('*.php')->in($path) as $file) {
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
    protected function namespaceToserviceId($namespace)
    {
        return str_replace('\\', '.', strtolower($namespace));
    }

    protected function replaceSelfKey()
    {

    }

    /**
     * @param Application    $app
     * @param ControllerInfo $controllerInfo
     * @param $callback
     * @return callable|string
     */
    protected function fixCallback(Application $app, ControllerInfo $controllerInfo, $callback)
    {
        if (is_string($callback)) {
            $matches = array();

            // controller as service callback
            if (preg_match('/^([^:]+):([^:]+)$/', $callback, $matches) === 1) {
                if ($matches[1] == '__self') {
                    $matches[1] = $controllerInfo->getserviceId();
                }

                return function () use ($app, $matches) {
                    return call_user_func_array(
                        array($app[$matches[1]], $matches[2]),
                        func_get_args()
                    );
                };
            }

            // static class call
            if (preg_match('/^([^:]+)::([^:]+)$/', $callback, $matches) === 1) {
                if ($matches[1] == '__self') {
                    $matches[1] = $controllerInfo->getNamespace();
                }

                return $matches[1] . '::' . $matches[2];
            }
        }

        return $callback;
    }
}
