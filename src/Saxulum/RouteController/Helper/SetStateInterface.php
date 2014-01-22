<?php

namespace Saxulum\RouteController\Helper;

interface SetStateInterface
{
    /**
     * @param array $array
     * @return $this
     */
    public static function __set_state(array $array);
}
