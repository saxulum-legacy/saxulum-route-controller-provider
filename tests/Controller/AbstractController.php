<?php

namespace Saxulum\Tests\RouteController\Controller;

abstract class AbstractController
{
    /**
     * @var \Pimple
     */
    protected $container;

    public function __construct(\Pimple $container)
    {
        $this->container = $container;
    }
}
