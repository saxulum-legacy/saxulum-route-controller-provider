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
}
