<?php

namespace Saxulum\Tests\RouteController\Provider;

use Saxulum\RouteController\Provider\RouteControllerProvider;
use Silex\Application;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\WebTestCase;

class RouteControllerProviderTest extends WebTestCase
{
    public function testHelloHansController()
    {
        $client = $this->createClient();

        $client->request('GET', '/en/hello/hans');
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('hello snah!', $client->getResponse()->getContent());
    }

    public function testHelloUrlController()
    {
        $client = $this->createClient();

        $client->request('GET', '/en/url');
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('http://localhost/en/hello/urs', $client->getResponse()->getContent());
    }

    public function testDummyController()
    {
        $client = $this->createClient();

        $client->request('GET', '/dummy');
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('http://localhost/dummy', $client->getResponse()->getContent());
    }

    public function createApplication()
    {
        $app = new Application();
        $app['debug'] = true;

        $app->register(new ServiceControllerServiceProvider());
        $app->register(new UrlGeneratorServiceProvider());
        $app->register(new RouteControllerProvider(), array(
            'route_controller_cache' => __DIR__ . '/../../../../../../cache/'
        ));

        $app['route_controller_paths'] = $app->share($app->extend('route_controller_paths', function ($paths) {
            $paths[] = realpath(__DIR__ . '/../Controller/');

            return $paths;
        }));

        return $app;
    }
}
