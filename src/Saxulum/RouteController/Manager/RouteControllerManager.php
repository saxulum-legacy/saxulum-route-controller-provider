<?php

namespace Saxulum\RouteController\Manager;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\Reader;
use Saxulum\RouteController\Annotation\Route;
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

                return $controllerReflectionClass->newInstanceArgs(array($app));
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
                            if ($callbackParts[0] == '__self__') {
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
                            if ($callbackParts[0] == '__self__') {
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
                            if ($callbackParts[0] == '__self__') {
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
     * @param  \ReflectionClass $controllerReflection
     * @return ControllerInfo
     */
    protected function getControllerInfo(\ReflectionClass $controllerReflection)
    {
        $methodInfos = array();

        $methodRouteAnnotations = array();
        foreach ($controllerReflection->getMethods() as $reflectionMethod) {
            if ($reflectionMethod->isPublic()) {
                $methodAnnotations = $this
                    ->annotationReader
                    ->getMethodAnnotations($reflectionMethod)
                ;

                $routeAnnotation = null;
                foreach ($methodAnnotations as $methodAnnotation) {
                    if ($methodAnnotation instanceof Route) {
                        $routeAnnotation = $methodAnnotation;
                        break;
                    }
                }

                if (!is_null($routeAnnotation)) {
                    $methodInfos[] = new MethodInfo(
                        $reflectionMethod->getName(),
                        new AnnotationInfo($routeAnnotation)
                    );
                }
            }
        }

        $classAnnotation = $this
            ->annotationReader
            ->getClassAnnotations($controllerReflection)
        ;

        $routeAnnotation = null;
        foreach ($classAnnotation as $classAnnotation) {
            if ($classAnnotation instanceof Route) {
                $routeAnnotation = $classAnnotation;
                break;
            }
        }

        $classNamespace = $controllerReflection->getName();

        return new ControllerInfo(
            $classNamespace,
            $this->namespaceToServiceKey($classNamespace),
            new AnnotationInfo($routeAnnotation),
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
            $controllerNamespaces = $this->findClasses($file);
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
     * @return bool|string
     */
    protected function findClasses(SplFileInfo $file)
    {
        $allreadyDeclaredClasses = get_declared_classes();
        include $file->getPathname();

        return array_diff(get_declared_classes(), $allreadyDeclaredClasses);
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
