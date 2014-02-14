<?php

namespace Saxulum\Tests\RouteController\Controller\SubDir;

use Saxulum\RouteController\Annotation\DI;
use Saxulum\RouteController\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGenerator;

class Test3Controller
{
    /**
     * @var UrlGenerator
     */
    protected $urlGenerator;

    /**
     * @DI(serviceIds={"url_generator"})
     * @param UrlGenerator $urlGenerator
     */
    public function setUrlGenerator(UrlGenerator $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @Route("/dummy", bind="dummy")
     */
    public function dummyAction()
    {
        return $this->urlGenerator->generate('dummy', array(), true);
    }
}
