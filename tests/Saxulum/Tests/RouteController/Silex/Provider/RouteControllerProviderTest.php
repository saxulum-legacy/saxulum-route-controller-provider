<?php

namespace Saxulum\Tests\RouteController\Silex\Provider;

use Saxulum\RouteController\Silex\Provider\RouteControllerProvider;
use Silex\Application;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\WebTestCase;

class RouteControllerProviderTest extends WebTestCase
{
    public function testController()
    {
        $client = $this->createClient();

        $client->request('GET', '/en/hello/hans');
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('hello snah!', $client->getResponse()->getContent());
    }

    public function createApplication()
    {
        $app = new Application();
        $app['debug'] = true;

        $app->register(new ServiceControllerServiceProvider());
        $app->register(new RouteControllerProvider());

        $app['route_controller_paths'] = $app->share($app->extend('route_controller_paths', function ($paths) {
            $paths[] = realpath(__DIR__ . '/../../Controller/');

            return $paths;
        }));

        return $app;
    }
}
