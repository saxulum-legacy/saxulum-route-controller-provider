<?php

namespace Saxulum\Tests\RouteController\Controller;

use Saxulum\RouteController\Annotation\Route;
use Saxulum\RouteController\Annotation\Callback;
use Saxulum\RouteController\Annotation\Convert;

/**
 * @Route("/{_locale}")
 */
class TestController extends AbstractController
{
    /**
     * @Route("/hello/{name}",
     *      bind="hello_world",
     *      asserts={"name"="\w+"},
     *      values={"name"="world"},
     *      converters={
     *          @Convert("name", callback=@Callback("__self__:convertName"))
     *      },
     *      method="GET",
     *      requireHttp=false,
     *      requireHttps=false,
     *      before={
     *          @Callback("__self__:beforeFirst"),
     *          @Callback("__self__:beforeSecond")
     *      },
     *      after={
     *          @Callback("__self__:afterFirst"),
     *          @Callback("__self__:afterSecond")
     *      }
     * )
     */
    public function testAction($name)
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

    public function beforeFirst()
    {

    }

    public function beforeSecond()
    {

    }

    public function afterFirst()
    {

    }

    public function afterSecond()
    {

    }
}
