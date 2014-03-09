<?php

namespace Saxulum\RouteController\Manager;

use Saxulum\RouteController\Annotation\Callback as CallbackAnnotation;
use Saxulum\RouteController\Annotation\Convert;
use Saxulum\RouteController\Annotation\Route;
use Saxulum\RouteController\Helper\ClassInfo;
use Saxulum\RouteController\Helper\MethodInfo;

class RouteManager
{
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
                $nodes = array_merge($nodes, $this->prepareControllerBindNode($route));
                $nodes = array_merge($nodes, $this->prepareControllerAsserts($route));
                $nodes = array_merge($nodes, $this->prepareControllerValues($route));
                $nodes = array_merge($nodes, $this->prepareControllerConverters($classInfo, $route));
                $nodes = array_merge($nodes, $this->prepareControllerMethod($route));
                $nodes = array_merge($nodes, $this->prepareControllerIsRequiredHttp($route));
                $nodes = array_merge($nodes, $this->prepareControllerIsRequiredHttps($route));
                $nodes = array_merge($nodes, $this->prepareControllerBefore($classInfo, $route));
                $nodes = array_merge($nodes, $this->prepareControllerAfter($classInfo, $route));
            }
        }

        $nodes[] = $this->prepareControllersMountNode($classInfo);

        return $nodes;
    }

    /**
     * @return \PHPParser_Node_Expr_Assign
     */
    protected function prepareControllersNode()
    {
        return new \PHPParser_Node_Expr_Assign(
            new \PHPParser_Node_Expr_Variable('controllers'),
            new \PHPParser_Node_Expr_ArrayDimFetch(
                new \PHPParser_Node_Expr_Variable('app'),
                new \PHPParser_Node_Scalar_String('controllers_factory')
            ),
            array(
                'comments' => array(
                    new \PHPParser_Comment("\n\n"),
                    new \PHPParser_Comment('/** @var Silex\ControllerCollection $controllers */'),
                )
            )
        );
    }

    /**
     * @param  ClassInfo                   $classInfo
     * @param  MethodInfo                  $methodInfo
     * @param  Route                       $route
     * @return \PHPParser_Node_Expr_Assign
     */
    protected function prepareControllerNode(ClassInfo $classInfo, MethodInfo $methodInfo, Route $route)
    {
        return new \PHPParser_Node_Expr_Assign(
            new \PHPParser_Node_Expr_Variable('controller'),
            new \PHPParser_Node_Expr_MethodCall(
                new \PHPParser_Node_Expr_Variable('controllers'),
                'match',
                array(
                    new \PHPParser_Node_Arg(
                        new \PHPParser_Node_Scalar_String($route->match)
                    ),
                    new \PHPParser_Node_Arg(
                        new \PHPParser_Node_Scalar_String(
                            $classInfo->getServiceId() . ':' . $methodInfo->getName()
                        )
                    )
                )
            ),
            array(
                'comments' => array(
                    new \PHPParser_Comment("\n\n"),
                    new \PHPParser_Comment('// '. $classInfo->getServiceId() . ':' . $methodInfo->getName()),
                )
            )
        );
    }

    /**
     * @param  Route             $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerBindNode(Route $route)
    {
        $nodes = array();
        if (!is_null($route->bind)) {
            $nodes[] = new \PHPParser_Node_Expr_MethodCall(
                new \PHPParser_Node_Expr_Variable('controller'),
                'bind',
                array(
                    new \PHPParser_Node_Arg(
                        new \PHPParser_Node_Scalar_String($route->bind)
                    ),
                )
            );
        }

        return $nodes;
    }

    /**
     * @param  Route             $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerAsserts(Route $route)
    {
        $nodes = array();
        foreach ($route->asserts as $variable => $regexp) {
            $nodes[] = new \PHPParser_Node_Expr_MethodCall(
                new \PHPParser_Node_Expr_Variable('controller'),
                'assert',
                array(
                    new \PHPParser_Node_Arg(
                        new \PHPParser_Node_Scalar_String($variable)
                    ),
                    new \PHPParser_Node_Arg(
                        new \PHPParser_Node_Scalar_String($regexp)
                    )
                )
            );
        }

        return $nodes;
    }

    /**
     * @param  Route             $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerValues(Route $route)
    {
        $nodes = array();
        foreach ($route->values as $variable => $default) {
            $nodes[] = new \PHPParser_Node_Expr_MethodCall(
                new \PHPParser_Node_Expr_Variable('controller'),
                'value',
                array(
                    new \PHPParser_Node_Arg(
                        new \PHPParser_Node_Scalar_String($variable)
                    ),
                    new \PHPParser_Node_Arg(
                        new \PHPParser_Node_Scalar_String($default)
                    )
                )
            );
        }

        return $nodes;
    }

    /**
     * @param  ClassInfo         $classInfo
     * @param  Route             $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerConverters(ClassInfo $classInfo, Route $route)
    {
        $nodes = array();
        foreach ($route->converters as $converter) {
            /** @var Convert $converter */

            $callbackNode = $this->prepareControllerConverterCallbackNode(
                $classInfo,
                $converter->variable,
                $converter->callback->callback
            );

            if (!is_null($callbackNode)) {
                $nodes[] = new \PHPParser_Node_Expr_MethodCall(
                    new \PHPParser_Node_Expr_Variable('controller'),
                    'convert',
                    array(
                        new \PHPParser_Node_Arg(
                            new \PHPParser_Node_Scalar_String($converter->variable)
                        ),
                        new \PHPParser_Node_Arg(
                            $callbackNode
                        )
                    )
                );
            }
        }

        return $nodes;
    }

    /**
     * @param  ClassInfo                 $classInfo
     * @param  string                    $variable
     * @param  callable                  $callback
     * @return null|\PHPParser_Node_Expr
     */
    protected function prepareControllerConverterCallbackNode(ClassInfo $classInfo, $variable, $callback)
    {
        if (is_array($callback)) {
            return $this->prepareControllerConverterArrayCallbackNode($classInfo, $callback);
        }

        if (is_scalar($callback)) {
            return $this->prepareControllerConverterScalarCallbackNode($classInfo, $variable, $callback);
        }

        return null;
    }

    /**
     * @param  ClassInfo            $classInfo
     * @param  array                $callback
     * @return \PHPParser_Node_Expr
     */
    protected function prepareControllerConverterArrayCallbackNode(ClassInfo $classInfo, array $callback)
    {
        if ($callback[0] == '__self') {
            $callback[0] = $classInfo->getName();
        }

        return new \PHPParser_Node_Expr_Array(
            new \PHPParser_Node_Expr_ArrayItem(
                new \PHPParser_Node_Scalar_String($callback[0])
            ),
            new \PHPParser_Node_Expr_ArrayItem(
                new \PHPParser_Node_Scalar_String($callback[1])
            )
        );
    }

    /**
     * @param  ClassInfo            $classInfo
     * @param $variable
     * @param $callback
     * @return \PHPParser_Node_Expr
     */
    protected function prepareControllerConverterScalarCallbackNode(ClassInfo $classInfo, $variable, $callback)
    {
        $matches = array();

        if (preg_match('/^([^:]+):([^:]+)$/', $callback, $matches) === 1) {

            if ($matches[1] == '__self') {
                $matches[1] = $classInfo->getServiceId();
            }

            return $this->prepareControllerConverterClosure(
                $variable,
                $matches[1],
                $matches[2]
            );
        }

        if (preg_match('/^([^:]+)::([^:]+)$/', $callback, $matches) === 1) {

            if ($matches[1] == '__self') {
                $matches[1] = $classInfo->getName();
            }

            return new \PHPParser_Node_Scalar_String($matches[1] . '::' . $matches[2]);
        }

        return new \PHPParser_Node_Scalar_String($callback);
    }

    /**
     * @param  string                       $variable
     * @param  string                       $serviceId
     * @param  string                       $methodName
     * @return \PHPParser_Node_Expr_Closure
     */
    protected function prepareControllerConverterClosure($variable, $serviceId, $methodName)
    {
        return  new \PHPParser_Node_Expr_Closure(
            array(
                'params' => array(
                    new \PHPParser_Node_Expr_Variable($variable)
                ),
                'uses' => array(
                    new \PHPParser_Node_Expr_ClosureUse('app')
                ),
                'stmts' => array(
                    new \PHPParser_Node_Stmt_Return(
                        new \PHPParser_Node_Expr_MethodCall(
                            new \PHPParser_Node_Expr_ArrayDimFetch(
                                new \PHPParser_Node_Expr_Variable('app'),
                                new \PHPParser_Node_Scalar_String($serviceId)
                            ),
                            $methodName,
                            array(
                                new \PHPParser_Node_Expr_Variable($variable)
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * @param  Route             $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerMethod(Route $route)
    {
        $nodes = array();
        if (!is_null($route->method)) {
            $nodes[] = new \PHPParser_Node_Expr_MethodCall(
                new \PHPParser_Node_Expr_Variable('controller'),
                'method',
                array(
                    new \PHPParser_Node_Arg(
                        new \PHPParser_Node_Scalar_String($route->method)
                    ),
                )
            );
        }

        return $nodes;
    }

    /**
     * @param  Route             $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerIsRequiredHttp(Route $route)
    {
        $nodes = array();
        if ($route->requireHttp) {
            $nodes[] = new \PHPParser_Node_Expr_MethodCall(
                new \PHPParser_Node_Expr_Variable('controller'),
                'requireHttp'
            );
        }

        return $nodes;
    }

    /**
     * @param  Route             $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerIsRequiredHttps(Route $route)
    {
        $nodes = array();
        if ($route->requireHttps) {
            $nodes[] = new \PHPParser_Node_Expr_MethodCall(
                new \PHPParser_Node_Expr_Variable('controller'),
                'requireHttps'
            );
        }

        return $nodes;
    }

    /**
     * @param  ClassInfo         $classInfo
     * @param  Route             $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerBefore(ClassInfo $classInfo, Route $route)
    {
        $nodes = array();
        foreach ($route->before as $before) {
            /** @var CallbackAnnotation $before */

            $callbackNode = $this->prepareControllerBeforeCallbackNode(
                $classInfo,
                $before->callback
            );

            if (!is_null($callbackNode)) {
                $nodes[] = new \PHPParser_Node_Expr_MethodCall(
                    new \PHPParser_Node_Expr_Variable('controller'),
                    'before',
                    array(
                        new \PHPParser_Node_Arg(
                            $callbackNode
                        )
                    )
                );
            }
        }

        return $nodes;
    }

    /**
     * @param  ClassInfo                 $classInfo
     * @param $callback
     * @return null|\PHPParser_Node_Expr
     */
    protected function prepareControllerBeforeCallbackNode(ClassInfo $classInfo, $callback)
    {
        if (is_array($callback)) {
            return $this->prepareControllerBeforeArrayCallbackNode($classInfo, $callback);
        }

        if (is_scalar($callback)) {
            return $this->prepareControllerBeforeScalarCallbackNode($classInfo, $callback);
        }

        return null;
    }

    /**
     * @param  ClassInfo            $classInfo
     * @param  array                $callback
     * @return \PHPParser_Node_Expr
     */
    protected function prepareControllerBeforeArrayCallbackNode(ClassInfo $classInfo, array $callback)
    {
        if ($callback[0] == '__self') {
            $callback[0] = $classInfo->getName();
        }

        return new \PHPParser_Node_Expr_Array(
            new \PHPParser_Node_Expr_ArrayItem(
                new \PHPParser_Node_Scalar_String($callback[0])
            ),
            new \PHPParser_Node_Expr_ArrayItem(
                new \PHPParser_Node_Scalar_String($callback[1])
            )
        );
    }

    /**
     * @param  ClassInfo            $classInfo
     * @param $callback
     * @return \PHPParser_Node_Expr
     */
    protected function prepareControllerBeforeScalarCallbackNode(ClassInfo $classInfo, $callback)
    {
        $matches = array();

        if (preg_match('/^([^:]+):([^:]+)$/', $callback, $matches) === 1) {

            if ($matches[1] == '__self') {
                $matches[1] = $classInfo->getServiceId();
            }

            return $this->prepareControllerBeforeClosure(
                $matches[1],
                $matches[2]
            );
        }

        if (preg_match('/^([^:]+)::([^:]+)$/', $callback, $matches) === 1) {

            if ($matches[1] == '__self') {
                $matches[1] = $classInfo->getName();
            }

            return new \PHPParser_Node_Scalar_String($matches[1] . '::' . $matches[2]);
        }

        return new \PHPParser_Node_Scalar_String($callback);
    }

    /**
     * @param  string                       $serviceId
     * @param  string                       $methodName
     * @return \PHPParser_Node_Expr_Closure
     */
    protected function prepareControllerBeforeClosure($serviceId, $methodName)
    {
        return  new \PHPParser_Node_Expr_Closure(
            array(
                'params' => array(
                    new \PHPParser_Node_Param('request', '',
                        new \PHPParser_Node_Name('Symfony\Component\HttpFoundation\Request')
                    )
                ),
                'uses' => array(
                    new \PHPParser_Node_Expr_ClosureUse('app')
                ),
                'stmts' => array(
                    new \PHPParser_Node_Stmt_Return(
                        new \PHPParser_Node_Expr_MethodCall(
                            new \PHPParser_Node_Expr_ArrayDimFetch(
                                new \PHPParser_Node_Expr_Variable('app'),
                                new \PHPParser_Node_Scalar_String($serviceId)
                            ),
                            $methodName,
                            array(
                                new \PHPParser_Node_Expr_Variable('request')
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * @param  ClassInfo         $classInfo
     * @param  Route             $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerAfter(ClassInfo $classInfo, Route $route)
    {
        $nodes = array();
        foreach ($route->after as $after) {
            /** @var CallbackAnnotation $after */

            $callbackNode = $this->prepareControllerAfterCallbackNode(
                $classInfo,
                $after->callback
            );

            if (!is_null($callbackNode)) {
                $nodes[] = new \PHPParser_Node_Expr_MethodCall(
                    new \PHPParser_Node_Expr_Variable('controller'),
                    'after',
                    array(
                        new \PHPParser_Node_Arg(
                            $callbackNode
                        )
                    )
                );
            }
        }

        return $nodes;
    }

    /**
     * @param  ClassInfo                 $classInfo
     * @param $callback
     * @return null|\PHPParser_Node_Expr
     */
    protected function prepareControllerAfterCallbackNode(ClassInfo $classInfo, $callback)
    {
        if (is_array($callback)) {
            return $this->prepareControllerAfterArrayCallbackNode($classInfo, $callback);
        }

        if (is_scalar($callback)) {
            return $this->prepareControllerAfterScalarCallbackNode($classInfo, $callback);
        }

        return null;
    }

    /**
     * @param  ClassInfo            $classInfo
     * @param  array                $callback
     * @return \PHPParser_Node_Expr
     */
    protected function prepareControllerAfterArrayCallbackNode(ClassInfo $classInfo, array $callback)
    {
        if ($callback[0] == '__self') {
            $callback[0] = $classInfo->getName();
        }

        return new \PHPParser_Node_Expr_Array(
            new \PHPParser_Node_Expr_ArrayItem(
                new \PHPParser_Node_Scalar_String($callback[0])
            ),
            new \PHPParser_Node_Expr_ArrayItem(
                new \PHPParser_Node_Scalar_String($callback[1])
            )
        );
    }

    /**
     * @param  ClassInfo            $classInfo
     * @param $callback
     * @return \PHPParser_Node_Expr
     */
    protected function prepareControllerAfterScalarCallbackNode(ClassInfo $classInfo, $callback)
    {
        $matches = array();

        if (preg_match('/^([^:]+):([^:]+)$/', $callback, $matches) === 1) {

            if ($matches[1] == '__self') {
                $matches[1] = $classInfo->getServiceId();
            }

            return $this->prepareControllerAfterClosure(
                $matches[1],
                $matches[2]
            );
        }

        if (preg_match('/^([^:]+)::([^:]+)$/', $callback, $matches) === 1) {

            if ($matches[1] == '__self') {
                $matches[1] = $classInfo->getName();
            }

            return new \PHPParser_Node_Scalar_String($matches[1] . '::' . $matches[2]);
        }

        return new \PHPParser_Node_Scalar_String($callback);
    }

    /**
     * @param  string                       $serviceId
     * @param  string                       $methodName
     * @return \PHPParser_Node_Expr_Closure
     */
    protected function prepareControllerAfterClosure($serviceId, $methodName)
    {
        return  new \PHPParser_Node_Expr_Closure(
            array(
                'params' => array(
                    new \PHPParser_Node_Param('request', '',
                        new \PHPParser_Node_Name('Symfony\Component\HttpFoundation\Request')
                    ),
                    new \PHPParser_Node_Param('response', '',
                        new \PHPParser_Node_Name('Symfony\Component\HttpFoundation\Response')
                    )
                ),
                'uses' => array(
                    new \PHPParser_Node_Expr_ClosureUse('app')
                ),
                'stmts' => array(
                    new \PHPParser_Node_Stmt_Return(
                        new \PHPParser_Node_Expr_MethodCall(
                            new \PHPParser_Node_Expr_ArrayDimFetch(
                                new \PHPParser_Node_Expr_Variable('app'),
                                new \PHPParser_Node_Scalar_String($serviceId)
                            ),
                            $methodName,
                            array(
                                new \PHPParser_Node_Expr_Variable('request'),
                                new \PHPParser_Node_Expr_Variable('response')
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * @param  ClassInfo                       $classInfo
     * @return \PHPParser_Node_Expr_MethodCall
     */
    protected function prepareControllersMountNode(ClassInfo $classInfo)
    {
        $mount = '';

        $route = $classInfo->getFirstAnnotationInstanceof(
            'Saxulum\\RouteController\\Annotation\\Route'
        );

        if (!is_null($route)) {
            $mount = $route->match;
        }

        return new \PHPParser_Node_Expr_MethodCall(
            new \PHPParser_Node_Expr_Variable('app'),
            'mount',
            array(
                new \PHPParser_Node_Arg(
                    new \PHPParser_Node_Scalar_String($mount)
                ),
                new \PHPParser_Node_Arg(
                    new \PHPParser_Node_Expr_Variable('controllers')
                )
            ),
            array(
                'comments' => array(
                    new \PHPParser_Comment("\n\n"),
                    new \PHPParser_Comment('// mount controllers'),
                )
            )
        );
    }
}
