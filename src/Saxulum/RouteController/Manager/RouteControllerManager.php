<?php

namespace Saxulum\RouteController\Manager;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\Reader;
use Saxulum\RouteController\Annotation\DI;
use Saxulum\RouteController\Annotation\Route;
use Saxulum\ClassFinder\ClassFinder;
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
     * @var null|string
     */
    protected $cache;

    /**
     * @param array       $paths
     * @param Reader      $annotationReader
     * @param null|string $cache
     */
    public function __construct(
        array $paths = array(),
        Reader $annotationReader = null,
        $cache
    ) {
        $this->paths = $paths;
        $this->annotationReader = $annotationReader;
        $this->cache = $cache;

        if(substr($this->cache, -1) == '/') {
            $this->cache = substr($this->cache, 0, -1);
        }

        if (is_null($this->annotationReader)) {
            $this->annotationReader = new AnnotationReader();
        }

        if (!is_null($this->cache) && !is_dir($this->cache)) {
            mkdir($this->cache, 0777, true);
        }
    }

    /**
     * @param Application $app
     */
    public function boot(Application $app)
    {
        $cacheFileName = 'saxulum-route-controller.php';

        $cacheFile = '';

        if(!is_null($this->cache)) {
            $cacheFile = $this->cache . '/' . $cacheFileName;
        }

        if(!$cacheFile) {
            $cacheFile = tempnam(sys_get_temp_dir(), $cacheFileName);
        }


        if ($app['debug'] || !file_exists($cacheFile)) {
            $controllerInfos = $this->getControllerInfos();

            $serviceManager = new ServiceManager();
            $routeManager = new RouteManager();

            $prettyPrinter = new \PHPParser_PrettyPrinter_Default();

            $code = "<?php\n\n";

            foreach ($controllerInfos as $controllerInfo) {
                $code .= $prettyPrinter->prettyPrint($serviceManager->generateCode($controllerInfo));
                $code .= "\n\n";
            }

            foreach ($controllerInfos as $controllerInfo) {
                $code .= $prettyPrinter->prettyPrint($routeManager->generateCode($controllerInfo));
                $code .= "\n\n";
            }

            file_put_contents($cacheFile, $code);
        }

        require $cacheFile;
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
                        $this->addClosureForServiceCallback(
                            $app,
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
                        $this->addClosureForServiceCallback(
                            $app, $before->getCallback()
                        )
                    );
                }
                foreach ($route->getAfter() as $after) {
                    $controller->after(
                        $this->addClosureForServiceCallback(
                            $app, $after->getCallback()
                        )
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
        $controllerInfo = $this->getClassInfo($reflectionClass);

        $gotRoute = false;
        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $methodInfo = $this->getMethodInfo($reflectionMethod, $controllerInfo);
            if (!is_null($methodInfo)) {
                if (!is_null($methodInfo->getAnnotationInfo()->getRoute())) {
                    $gotRoute = true;
                }
                $controllerInfo->addMethodInfo($methodInfo);
            }
        }

        if (!$gotRoute) {
            return null;
        }

        return $controllerInfo;
    }

    /**
     * @param  \ReflectionClass $reflectionClass
     * @return ControllerInfo
     */
    protected function getClassInfo(\ReflectionClass $reflectionClass)
    {
        $classAnnotations = $this
            ->annotationReader
            ->getClassAnnotations($reflectionClass)
        ;

        $routeAnnotation = null;
        $diAnnotation = null;

        foreach ($classAnnotations as $classAnnotation) {
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
            new AnnotationInfo($routeAnnotation, $diAnnotation)
        );
    }

    /**
     * @param  \ReflectionMethod $reflectionMethod
     * @param  ControllerInfo    $controllerInfo
     * @return null|MethodInfo
     */
    protected function getMethodInfo(
        \ReflectionMethod $reflectionMethod,
        ControllerInfo $controllerInfo
    ) {
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
                    foreach ($routeAnnotation->getConverters() as $converter) {
                        $converter->getCallback()->setCallback($this->replaceSelfKey(
                            $converter->getCallback()->getCallback(),
                            $controllerInfo
                        ));
                    }
                    foreach ($routeAnnotation->getBefore() as $before) {
                        $before->setCallback($this->replaceSelfKey(
                            $before->getCallback(),
                            $controllerInfo
                        ));
                    }
                    foreach ($routeAnnotation->getAfter() as $after) {
                        $after->setCallback($this->replaceSelfKey(
                            $after->getCallback(),
                            $controllerInfo
                        ));
                    }
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

    /**
     * @param  callable       $callback
     * @param  ControllerInfo $controllerInfo
     * @return string
     */
    protected function replaceSelfKey($callback, ControllerInfo $controllerInfo)
    {
        if (is_string($callback)) {
            $matches = array();

            // controller as service callback
            if (preg_match('/^([^:]+):([^:]+)$/', $callback, $matches) === 1) {
                if ($matches[1] == '__self') {
                    $matches[1] = $controllerInfo->getserviceId();
                }

                return $matches[1] . ':' . $matches[2];
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

    /**
     * @param  Application $app
     * @param  callable    $callback
     * @return callable
     */
    protected function addClosureForServiceCallback(Application $app, $callback)
    {
        if (is_string($callback)) {
            $matches = array();

            // controller as service callback
            if (preg_match('/^([^:]+):([^:]+)$/', $callback, $matches) === 1) {
                return function () use ($app, $matches) {
                    return call_user_func_array(
                        array($app[$matches[1]], $matches[2]),
                        func_get_args()
                    );
                };
            }
        }

        return $callback;
    }
}
