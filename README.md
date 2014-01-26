saxulum-route-controller-provider
=================================

**works with plain silex-php**

[![Build Status](https://api.travis-ci.org/saxulum/saxulum-route-controller-provider.png?branch=master)](https://travis-ci.org/saxulum/saxulum-route-controller-provider)
[![Total Downloads](https://poser.pugx.org/saxulum/saxulum-route-controller-provider/downloads.png)](https://packagist.org/packages/saxulum/saxulum-route-controller-provider)
[![Latest Stable Version](https://poser.pugx.org/saxulum/saxulum-route-controller-provider/v/stable.png)](https://packagist.org/packages/saxulum/saxulum-route-controller-provider)

Features
--------

* Register Controller as Service using Annotations

Requirements
------------

* php >=5.3
* Doctrine Annotations >=1.1
* Symfony Finder Component >=2.3
* Silex >= 1.1

Installation
------------

Through [Composer](http://getcomposer.org) as [saxulum/saxulum-route-controller-provider][2].

The [ServiceControllerServiceProvider][1] from silex itself is needed!

With controller info cache (faster)

```php
use Saxulum\RouteController\Provider\RouteControllerProvider;
use Silex\Provider\ServiceControllerServiceProvider;

$app->register(new ServiceControllerServiceProvider());
$app->register(new RouteControllerProvider(), array(
    'route_controller_cache' => '/path/to/cache/'
));
```

Without controller info cache (slower)

```{.php}
use Saxulum\RouteController\Provider\RouteControllerProvider;
use Silex\Provider\ServiceControllerServiceProvider;

$app->register(new ServiceControllerServiceProvider());
$app->register(new RouteControllerProvider());
```

Add the controller paths

```{.php}
$app['route_controller_paths'] = $app->share($app->extend('route_controller_paths', function ($paths) {
    $paths[] = '/path/to/the/controllers';

    return $paths;
}));
```

Usage
-----

```{.php}
<?php

namespace Saxulum\Tests\RouteController\Controller;

use Saxulum\RouteController\Annotation\Callback;
use Saxulum\RouteController\Annotation\Convert;
use Saxulum\RouteController\Annotation\DI;
use Saxulum\RouteController\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/{_locale}")
 * @DI(injectContainer=true)
 */
class TestController
{
    /**
     * @var \Pimple
     */
    protected $container;

    public function __construct(\Pimple $container)
    {
        $this->container = $container;
    }

    /**
     * @Route("/hello/{name}",
     *      bind="hello_name",
     *      asserts={"name"="\w+"},
     *      values={"name"="world"},
     *      converters={
     *          @Convert("name", callback=@Callback("__self:convertName"))
     *      },
     *      method="GET",
     *      requireHttp=false,
     *      requireHttps=false,
     *      before={
     *          @Callback("__self:beforeFirst"),
     *          @Callback("__self::beforeSecond")
     *      },
     *      after={
     *          @Callback("__self:afterFirst"),
     *          @Callback("__self::afterSecond")
     *      }
     * )
     */
    public function hellonameAction($name)
    {
        return 'hello ' . $name . '!';
    }

    public function convertName($name)
    {
        $newName = '';
        $nameLength = strlen($name);
        for ($i = 0; $nameLength > $i; $i++) {
            $newName .= $name[$nameLength - 1 - $i];
        }

        return $newName;
    }

    public function beforeFirst(Request $request)
    {

    }

    public static function beforeSecond(Request $request)
    {

    }

    public function afterFirst(Request $request, Response $response)
    {

    }

    public static function afterSecond(Request $request, Response $response)
    {

    }
}
```

```{.php}
<?php

namespace Saxulum\Tests\RouteController\Controller;

use Saxulum\RouteController\Annotation\DI;
use Saxulum\RouteController\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGenerator;

/**
 * @Route("/{_locale}")
 * @DI(serviceIds={"url_generator"})
 */
class Test2Controller
{
    /**
     * @var UrlGenerator
     */
    protected $urlGenerator;

    public function __construct(UrlGenerator $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @Route("/hello/url", bind="hello_url")
     */
    public function hellourlAction()
    {
        return $this->urlGenerator->generate('hello_name', array('name' => 'urs'), true);
    }
}
```

[1]: http://silex.sensiolabs.org/doc/providers/service_controller.html
[2]: https://packagist.org/packages/saxulum/saxulum-route-controller-provider