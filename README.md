saxulum-route-controller-provider
=================================

**works with plain silex-php**

[![Build Status](https://api.travis-ci.org/saxulum/saxulum-route-controller-provider.png?branch=master)](https://travis-ci.org/saxulum/saxulum-route-controller-provider)
[![Total Downloads](https://poser.pugx.org/saxulum/saxulum-route-controller-provider/downloads.png)](https://packagist.org/packages/saxulum/saxulum-route-controller-provider)
[![Latest Stable Version](https://poser.pugx.org/saxulum/saxulum-route-controller-provider/v/stable.png)](https://packagist.org/packages/saxulum/saxulum-route-controller-provider)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/saxulum/saxulum-route-controller-provider/badges/quality-score.png?s=6539e5892cc965ef82ac8ec929442c544a4e02a5)](https://scrutinizer-ci.com/g/saxulum/saxulum-route-controller-provider/)

Features
--------

* Register Controller as Service using Annotations

Requirements
------------

* php >=5.3
* Doctrine Annotations >=1.1
* PHP Parser >=0.9,<1.0
* Saxulum ClassFinder >=1.0
* Symfony Finder Component >=2.3
* Silex >= 1.1

Installation
------------

Through [Composer](http://getcomposer.org) as [saxulum/saxulum-route-controller-provider][1].

### AnnotationRegistry

Add this line after you added the `autoload.php` from composer

```{.php}
\Doctrine\Common\Annotations\AnnotationRegistry::registerLoader(
    array($loader, 'loadClass')
);
```

### With defined cache dir

```{.php}
use Saxulum\RouteController\Provider\RouteControllerProvider;
use Silex\Provider\ServiceControllerServiceProvider;

$app->register(new ServiceControllerServiceProvider());
$app->register(new RouteControllerProvider(), array(
    'route_controller_cache' => '/path/to/cache/'
));
```

* `debug == true`: the cache file will be build at each load
* `debug == false`: the cache file will be build if not exists, delete it if its out of sync

### Without defined cache dir
probably slower, cause temp dir cleanups

```{.php}
use Saxulum\RouteController\Provider\RouteControllerProvider;
use Silex\Provider\ServiceControllerServiceProvider;

$app->register(new ServiceControllerServiceProvider());
$app->register(new RouteControllerProvider());
```

* `debug == true`: the cache file will be build at each load
* `debug == false`: the cache file will be build if not exists, delete it if its out of sync

### Add the controller paths

```{.php}
$app['route_controller_paths'] = $app->share(
    $app->extend('route_controller_paths', function ($paths) {
        $paths[] = '/path/to/the/controllers';

        return $paths;
    })
);
```

Usage
-----

### Route Annotation

#### Controller

```{.php}
use Saxulum\RouteController\Annotation\Route;

/**
 * @Route("/{_locale}")
 */
```

#### Method

```{.php}
use Saxulum\RouteController\Annotation\Callback;
use Saxulum\RouteController\Annotation\Convert;
use Saxulum\RouteController\Annotation\Route;

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
```

* `__self` get replaced by the controller class.
* `__self:beforeFirst` calls the method `beforeFirst` on the controller instance
* `__self::beforeSecond` calls the static method `beforeSecond` on the controller

### Dependency Injection Annotation

If there is no DI annotation, the controller will be registred without
any dependencies as long there is at least one method route annotation.

#### Container Injection

```{.php}
use Saxulum\RouteController\Annotation\DI;

/**
 * @DI(injectContainer=true)
 */
```

#### Service Injection
```{.php}
use Saxulum\RouteController\Annotation\DI;

/**
 * @DI(serviceIds={"url_generator"})
 */
```

[1]: https://packagist.org/packages/saxulum/saxulum-route-controller-provider