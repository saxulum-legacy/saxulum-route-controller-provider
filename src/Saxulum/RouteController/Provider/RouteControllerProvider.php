<?php

namespace Saxulum\RouteController\Provider;

use Doctrine\Common\Annotations\AnnotationReader;
use Saxulum\RouteController\Manager\RouteControllerManager;
use Silex\Application;
use Silex\ServiceProviderInterface;

class RouteControllerProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['route_controller_cache'] = null;

        $app['route_controller_paths'] = $app->share(function () {
            $paths = array();

            return $paths;
        });

        $app['route_controller_annotation_reader'] = $app->share(function () {
            return new AnnotationReader();
        });

        $app['route_controller_manager'] = $app->share(function () use ($app) {
            return new RouteControllerManager(
                $app['route_controller_paths'],
                $app['route_controller_annotation_reader'],
                $app['route_controller_cache']
            );
        });
    }

    public function boot(Application $app)
    {
        $app['route_controller_manager']->boot($app);
    }
}
