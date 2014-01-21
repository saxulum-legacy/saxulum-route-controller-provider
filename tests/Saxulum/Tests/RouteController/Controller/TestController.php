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
class TestController extends AbstractController
{
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
