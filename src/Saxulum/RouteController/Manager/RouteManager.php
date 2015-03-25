<?php

namespace Saxulum\RouteController\Manager;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ClosureUse;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeAbstract;
use Saxulum\RouteController\Annotation\Callback as CallbackAnnotation;
use Saxulum\RouteController\Annotation\Convert;
use Saxulum\RouteController\Annotation\Route;
use Saxulum\AnnotationManager\Helper\ClassInfo;
use Saxulum\AnnotationManager\Helper\MethodInfo;

class RouteManager
{
    /**
     * @param  ClassInfo $classInfo
     * @return Expr[]
     */
    public function generateCode(ClassInfo $classInfo)
    {
        $nodes = array();
        $nodes[] = $this->prepareControllersNode();

        foreach ($classInfo->getMethodInfos() as $methodInfo) {
            $route = $methodInfo->getFirstAnnotationInstanceof(
                'Saxulum\\RouteController\\Annotation\\Route'
            );
            if (!is_null($route)) {
                $nodes[] = $this->prepareControllerNode($classInfo, $methodInfo, $route);
                $nodes = array_merge($nodes, $this->prepareControllerSimple($route, 'bind'));
                $nodes = array_merge($nodes, $this->prepareControllerComplex($route, 'asserts', 'assert'));
                $nodes = array_merge($nodes, $this->prepareControllerComplex($route, 'values', 'value'));
                $nodes = array_merge($nodes, $this->prepareControllerConverters($classInfo, $route));
                $nodes = array_merge($nodes, $this->prepareControllerSimple($route, 'method'));
                $nodes = array_merge($nodes, $this->prepareControllerBoolean($route, 'requireHttp'));
                $nodes = array_merge($nodes, $this->prepareControllerBoolean($route, 'requireHttps'));
                $nodes = array_merge($nodes, $this->prepareControllerBefore($classInfo, $route));
                $nodes = array_merge($nodes, $this->prepareControllerAfter($classInfo, $route));
            }
        }

        $nodes[] = $this->prepareControllersMountNode($classInfo);

        return $nodes;
    }

    /**
     * @return Assign
     */
    protected function prepareControllersNode()
    {
        return new Assign(
            new Variable('controllers'),
            new ArrayDimFetch(
                new Variable('app'),
                new String_('controllers_factory')
            )
        );
    }

    /**
     * @param  ClassInfo  $classInfo
     * @param  MethodInfo $methodInfo
     * @param  Route      $route
     * @return Assign
     */
    protected function prepareControllerNode(ClassInfo $classInfo, MethodInfo $methodInfo, Route $route)
    {
        return new Assign(
            new Variable('controller'),
            new MethodCall(
                new Variable('controllers'),
                'match',
                array(
                    new Arg(
                        $this->prepareScalarArg($route->value)
                    ),
                    new Arg(
                        $this->prepareScalarArg(
                            $classInfo->getServiceId() . ':' . $methodInfo->getName()
                        )
                    )
                )
            )
        );
    }

    /**
     * @param  Route          $route
     * @param  string         $property
     * @return NodeAbstract[]
     */
    protected function prepareControllerSimple(Route $route, $property)
    {
        $nodes = array();
        if (!is_null($route->$property)) {
            $nodes[] = new MethodCall(
                new Variable('controller'),
                $property,
                array(
                    new Arg(
                        $this->prepareScalarArg($route->$property)
                    ),
                )
            );
        }

        return $nodes;
    }

    /**
     * @param  Route          $route
     * @param  string         $property
     * @param  string         $method
     * @return NodeAbstract[]
     */
    protected function prepareControllerComplex(Route $route, $property, $method)
    {
        $nodes = array();
        foreach ($route->$property as $key => $element) {
            $nodes[] = new MethodCall(
                new Variable('controller'),
                $method,
                array(
                    new Arg(
                        $this->prepareScalarArg($key)
                    ),
                    new Arg(
                        $this->prepareScalarArg($element)
                    )
                )
            );
        }

        return $nodes;
    }

    /**
     * @param  ClassInfo      $classInfo
     * @param  Route          $route
     * @return NodeAbstract[]
     */
    protected function prepareControllerConverters(ClassInfo $classInfo, Route $route)
    {
        $nodes = array();
        foreach ($route->converters as $converter) {
            /** @var Convert $converter */

            $nodes[] = new MethodCall(
                new Variable('controller'),
                'convert',
                array(
                    new Arg(
                        $this->prepareScalarArg($converter->value)
                    ),
                    new Arg(
                        $this->prepareCallbackNode(
                            $classInfo,
                            $converter->callback->value,
                            'prepareControllerConverterClosure',
                            $converter->value
                        )
                    )
                )
            );
        }

        return $nodes;
    }

    /**
     * @param  ClassInfo   $classInfo
     * @param  string      $callback
     * @param  string      $callbackMethod
     * @param  string|null $value
     * @return Expr
     */
    protected function prepareCallbackNode(ClassInfo $classInfo, $callback, $callbackMethod, $value = null)
    {
        // controller as service callback
        if (preg_match('/^([^:]+):([^:]+)$/', $callback, $matches) === 1) {

            if ($matches[1] == '__self') {
                $matches[1] = $classInfo->getServiceId();
            }

            return  $this->$callbackMethod(
                $matches[1],
                $matches[2],
                $value
            );
        } elseif (preg_match('/^([^:]+)::([^:]+)$/', $callback, $matches) === 1) {

            if ($matches[1] == '__self') {
                $matches[1] = $classInfo->getName();
            }

            return new String_($matches[1] . '::' . $matches[2]);
        } else {
            return new String_($callback);
        }
    }

    /**
     * @param  string  $serviceId
     * @param  string  $methodName
     * @param  string  $variable
     * @return Closure
     */
    protected function prepareControllerConverterClosure($serviceId, $methodName, $variable)
    {
        return new Closure(
            array(
                'params' => array(
                    new Variable($variable)
                ),
                'uses' => array(
                    new ClosureUse('app')
                ),
                'stmts' => array(
                    new Return_(
                        new MethodCall(
                            new ArrayDimFetch(
                                new Variable('app'),
                                new String_($serviceId)
                            ),
                            $methodName,
                            array(
                                new Arg(
                                    new Variable($variable)
                                )
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * @param  Route          $route
     * @param  string         $property
     * @return NodeAbstract[]
     */
    protected function prepareControllerBoolean(Route $route, $property)
    {
        $nodes = array();
        if ($route->$property) {
            $nodes[] = new MethodCall(
                new Variable('controller'),
                $property
            );
        }

        return $nodes;
    }

    /**
     * @param  ClassInfo      $classInfo
     * @param  Route          $route
     * @return NodeAbstract[]
     */
    protected function prepareControllerBefore(ClassInfo $classInfo, Route $route)
    {
        $nodes = array();
        foreach ($route->before as $before) {
            $nodes[] = $this->prepareControllerCallback(
                $classInfo,
                $before,
                'before',
                'prepareControllerBeforeClosure'
            );
        }

        return $nodes;
    }

    /**
     * @param  ClassInfo          $classInfo
     * @param  CallbackAnnotation $annotation
     * @param  string             $method
     * @param  string             $callbackMethod
     * @return MethodCall
     */
    protected function prepareControllerCallback(ClassInfo $classInfo, CallbackAnnotation $annotation, $method, $callbackMethod)
    {
        /** @var CallbackAnnotation $annotation */

        return new MethodCall(
            new Variable('controller'),
            $method,
            array(
                new Arg(
                    $this->prepareCallbackNode(
                        $classInfo,
                        $annotation->value,
                        $callbackMethod
                    )
                )
            )
        );
    }

    /**
     * @param  string  $serviceId
     * @param  string  $methodName
     * @return Closure
     */
    protected function prepareControllerBeforeClosure($serviceId, $methodName)
    {
        return new Closure(
            array(
                'params' => array(
                    new Param(
                        'request',
                        null,
                        new Name('Symfony\Component\HttpFoundation\Request')
                    )
                ),
                'uses' => array(
                    new ClosureUse('app')
                ),
                'stmts' => array(
                    new Return_(
                        new MethodCall(
                            new ArrayDimFetch(
                                new Variable('app'),
                                new String_($serviceId)
                            ),
                            $methodName,
                            array(
                                new Arg(
                                    new Variable('request')
                                )
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * @param  ClassInfo      $classInfo
     * @param  Route          $route
     * @return NodeAbstract[]
     */
    protected function prepareControllerAfter(ClassInfo $classInfo, Route $route)
    {
        $nodes = array();
        foreach ($route->after as $after) {
            $nodes[] = $this->prepareControllerCallback(
                $classInfo,
                $after,
                'after',
                'prepareControllerAfterClosure'
            );
        }

        return $nodes;
    }

    /**
     * @param  string  $serviceId
     * @param  string  $methodName
     * @return Closure
     */
    protected function prepareControllerAfterClosure($serviceId, $methodName)
    {
        return new Closure(
            array(
                'params' => array(
                    new Param(
                        'request',
                        null,
                        new Name('Symfony\Component\HttpFoundation\Request')
                    ),
                    new Param(
                        'response',
                        null,
                        new Name('Symfony\Component\HttpFoundation\Response')
                    )
                ),
                'uses' => array(
                    new ClosureUse('app')
                ),
                'stmts' => array(
                    new Return_(
                        new MethodCall(
                            new ArrayDimFetch(
                                new Variable('app'),
                                new String_($serviceId)
                            ),
                            $methodName,
                            array(
                                new Arg(
                                    new Variable('request')
                                ),
                                new Arg(
                                    new Variable('response')
                                )
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * @param  ClassInfo  $classInfo
     * @return MethodCall
     */
    protected function prepareControllersMountNode(ClassInfo $classInfo)
    {
        $mount = '';

        $route = $classInfo->getFirstAnnotationInstanceof(
            'Saxulum\\RouteController\\Annotation\\Route'
        );

        if (!is_null($route)) {
            $mount = $route->value;
        }

        return new MethodCall(
            new Variable('app'),
            'mount',
            array(
                new Arg(
                    $this->prepareScalarArg($mount)
                ),
                new Arg(
                    new Variable('controllers')
                )
            )
        );
    }

    /**
     * @param $value
     * @return Expr
     */
    protected function prepareScalarArg($value)
    {
        if ($value === null) {
            return new Expr\ConstFetch(new Name('null'));
        }

        if (is_float($value)) {
            return new DNumber($value);
        }

        if (is_int($value)) {
            return new LNumber($value);
        }

        return new String_($value);
    }
}
