<?php

namespace Saxulum\RouteController\Provider;

use Doctrine\Common\Annotations\AnnotationReader;
use PhpParser\PrettyPrinter\Standard;
use Saxulum\AnnotationManager\Manager\AnnotationManager;
use Saxulum\RouteController\Manager\RouteControllerManager;
use Saxulum\RouteController\Manager\RouteManager;
use Saxulum\RouteController\Manager\ServiceManager;
use Silex\Application;
use Silex\ServiceProviderInterface;

class RouteControllerProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['route_controller_manager'] = $app->share(function () use ($app) {
            return new RouteControllerManager(
                $app['route_controller_paths'],
                $app['route_controller_cache'],
                $app['route_controller_cache_filename']
            );
        });

        $app['route_controller_paths'] = $app->share(function () {
            $paths = array();

            return $paths;
        });

        $app['route_controller_cache'] = null;

        $app['route_controller_cache_filename'] = 'saxulum-route-controller.php';

        $app['route_controller_annotation_reader'] = $app->share(function () {
            return new AnnotationReader();
        });

        $app['route_controller_annotation_manager'] = $app->share(function ($app) {
            return new AnnotationManager($app['route_controller_annotation_reader']);
        });

        $app['route_controller_service_manager'] = $app->share(function () {
            return new ServiceManager();
        });

        $app['route_controller_route_manager'] = $app->share(function () {
            return new RouteManager();
        });

        $app['route_controller_pretty_printer'] = $app->share(function () {
            return new Standard();
        });
    }

    public function boot(Application $app)
    {
        /** @var RouteControllerManager $routeControllerManager */
        $routeControllerManager = $app['route_controller_manager'];
        if (!$routeControllerManager->isCacheValid($app['debug'])) {
            $routeControllerManager->updateCache(
                $app['route_controller_annotation_manager'],
                $app['route_controller_service_manager'],
                $app['route_controller_route_manager'],
                $app['route_controller_pretty_printer']
            );
        }
        $routeControllerManager->loadCache($app);
    }
}
