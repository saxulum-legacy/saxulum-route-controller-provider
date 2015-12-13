<?php

namespace Saxulum\Tests\RouteController\Controller;

use Saxulum\RouteController\Annotation\DI;
use Saxulum\RouteController\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
     * @Route("/url/{route}", bind="url", values={"route"=null})
     */
    public function urlAction($route)
    {
        if (null ===  $route) {
            $route = 'hello_name';
        }

        return $this->urlGenerator->generate($route, array('name' => 'urs'), UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
