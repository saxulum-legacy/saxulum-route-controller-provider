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
     * @Route("/url", bind="url")
     */
    public function urlAction()
    {
        return $this->urlGenerator->generate('hello_name', array('name' => 'urs'), true);
    }
}
