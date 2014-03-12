<?php

namespace Saxulum\RouteController\Manager;

use Saxulum\AnnotationManager\Manager\AnnotationManager;
use Silex\Application;

class RouteControllerManager
{
    /**
     * @var array
     */
    protected $paths;

    /**
     * @var string
     */
    protected $cacheFile;

    public function __construct(
        array $paths = array(),
        $cacheDirectory,
        $cacheFileName
    ) {
        if (!is_null($cacheDirectory)) {
            if (substr($cacheDirectory, -1) == '/') {
                $cacheDirectory = substr($cacheDirectory, 0, -1);
            }

            if (!is_dir($cacheDirectory)) {
                mkdir($cacheDirectory, 0777, true);
            }
        } else {
            $cacheDirectory = sys_get_temp_dir();
        }

        $this->paths = $paths;
        $this->cacheFile = $cacheDirectory . '/' . $cacheFileName;
    }

    /**
     * @param  boolean $debug
     * @return bool
     */
    public function isCacheValid($debug)
    {
        if (!$debug && file_exists($this->cacheFile)) {
            return true;
        }

        return false;
    }

    /**
     * @param AnnotationManager                $annotationManager
     * @param ServiceManager                   $serviceManager
     * @param RouteManager                     $routeManager
     * @param \PHPParser_PrettyPrinter_Default $prettyPrinter
     */
    public function updateCache(
        AnnotationManager $annotationManager,
        ServiceManager $serviceManager,
        RouteManager $routeManager,
        \PHPParser_PrettyPrinter_Default $prettyPrinter
    ) {
        $classInfos = $annotationManager
            ->buildClassInfosBasedOnPaths($this->paths)
        ;

        $code = "<?php\n\n";

        foreach ($classInfos as $classInfo) {
            $code .= $prettyPrinter->prettyPrint($serviceManager->generateCode($classInfo));
            $code .= "\n\n";
        }

        foreach ($classInfos as $classInfo) {
            $code .= $prettyPrinter->prettyPrint($routeManager->generateCode($classInfo));
            $code .= "\n\n";
        }

        file_put_contents($this->cacheFile, $code);
    }

    /**
     * @param  Application     $app
     * @throws \LogicException
     */
    public function loadCache(Application $app)
    {
        if (!file_exists($this->cacheFile)) {
            throw new \LogicException("Can't load cache, if its not exists");
        }

        require $this->cacheFile;
    }
}
