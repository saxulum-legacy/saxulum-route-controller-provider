<?php

namespace Saxulum\Tests\RouteController\Controller\SubDir;

use Saxulum\RouteController\Annotation\Route;

class Test3Controller
{
    /**
     * @Route("/dummy", bind="dummy")
     */
    public function dummyAction()
    {
        return 'dummy';
    }
}
