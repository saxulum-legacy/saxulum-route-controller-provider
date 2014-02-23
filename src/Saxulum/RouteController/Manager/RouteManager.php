<?php

namespace Saxulum\RouteController\Manager;

use Saxulum\RouteController\Annotation\DI;
use Saxulum\RouteController\Annotation\Route;
use Saxulum\RouteController\Helper\ControllerInfo;
use Saxulum\RouteController\Helper\MethodInfo;

class RouteManager
{
    public function generateCode(ControllerInfo $controllerInfo)
    {
        $nodes = array();
        $nodes[] = $this->prepareControllersNode();

        foreach($controllerInfo->getMethodInfos() as $methodInfo) {
            $route = $methodInfo->getAnnotationInfo()->getRoute();
            if(!is_null($route)) {
                $nodes[] = $this->prepareControllerNode($controllerInfo, $methodInfo, $route);
                $nodes = array_merge($nodes, $this->prepareControllerBindNode($route));
                $nodes = array_merge($nodes, $this->prepareControllerAsserts($route));
                $nodes = array_merge($nodes, $this->prepareControllerValues($route));
                $nodes = array_merge($nodes, $this->prepareControllerConverters($route));
                $nodes = array_merge($nodes, $this->prepareControllerMethod($route));
                $nodes = array_merge($nodes, $this->prepareControllerIsRequiredHttp($route));
                $nodes = array_merge($nodes, $this->prepareControllerIsRequiredHttps($route));
                $nodes = array_merge($nodes, $this->prepareControllerBefore($route));
                $nodes = array_merge($nodes, $this->prepareControllerAfter($route));
            }
        }

        $nodes[] = $this->prepareControllersMountNode($controllerInfo);

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
            )
        );
    }

    /**
     * @param ControllerInfo $controllerInfo
     * @param MethodInfo $methodInfo
     * @param Route $route
     * @return \PHPParser_Node_Expr_Assign
     */
    protected function prepareControllerNode(ControllerInfo $controllerInfo, MethodInfo $methodInfo, Route $route)
    {
        return new \PHPParser_Node_Expr_Assign(
            new \PHPParser_Node_Expr_Variable('controller'),
            new \PHPParser_Node_Expr_MethodCall(
                new \PHPParser_Node_Expr_Variable('controllers'),
                'match',
                array(
                    new \PHPParser_Node_Arg(
                        new \PHPParser_Node_Scalar_String($route->getMatch())
                    ),
                    new \PHPParser_Node_Arg(
                        new \PHPParser_Node_Scalar_String(
                            $controllerInfo->getserviceId() . ':' . $methodInfo->getName()
                        )
                    )
                )
            ),
            array(
                'comments' => array(
                    new \PHPParser_Comment("\n\n"),
                    new \PHPParser_Comment('// '. $controllerInfo->getserviceId() . ':' . $methodInfo->getName()),
                )
            )
        );
    }

    /**
     * @param Route $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerBindNode(Route $route)
    {
        $nodes = array();
        if(!is_null($route->getBind())) {
            $nodes[] = new \PHPParser_Node_Expr_MethodCall(
                new \PHPParser_Node_Expr_Variable('controller'),
                'bind',
                array(
                    new \PHPParser_Node_Arg(
                        new \PHPParser_Node_Scalar_String($route->getBind())
                    ),
                )
            );
        }

        return $nodes;
    }

    /**
     * @param Route $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerAsserts(Route $route)
    {
        $nodes = array();
        foreach($route->getAsserts() as $variable => $regexp) {
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
     * @param Route $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerValues(Route $route)
    {
        $nodes = array();
        foreach($route->getValues() as $variable => $default) {
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
     * @param Route $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerConverters(Route $route)
    {
        $nodes = array();
        foreach($route->getConverters() as $converter) {

            $callback = $converter->getCallback()->getCallback();

            $matches = array();

            // controller as service callback
            if (preg_match('/^([^:]+):([^:]+)$/', $callback, $matches) === 1) {

                $callbackNode = $this->prepareControllerConverterClosure(
                    $converter->getVariable(),
                    $matches[1],
                    $matches[2]
                );
            } else {
                $callbackNode = new \PHPParser_Node_Scalar_String($callback);
            }

            $nodes[] = new \PHPParser_Node_Expr_MethodCall(
                new \PHPParser_Node_Expr_Variable('controller'),
                'convert',
                array(
                    new \PHPParser_Node_Arg(
                        new \PHPParser_Node_Scalar_String($converter->getVariable())
                    ),
                    new \PHPParser_Node_Arg(
                        $callbackNode
                    )
                )
            );
        }

        return $nodes;
    }

    /**
     * @param string $variable
     * @param string $serviceId
     * @param string $methodName
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
     * @param Route $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerMethod(Route $route)
    {
        $nodes = array();
        if(!is_null($route->getMethod())) {
            $nodes[] = new \PHPParser_Node_Expr_MethodCall(
                new \PHPParser_Node_Expr_Variable('controller'),
                'method',
                array(
                    new \PHPParser_Node_Arg(
                        new \PHPParser_Node_Scalar_String($route->getMethod())
                    ),
                )
            );
        }

        return $nodes;
    }

    /**
     * @param Route $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerIsRequiredHttp(Route $route)
    {
        $nodes = array();
        if($route->isRequireHttp()) {
            $nodes[] = new \PHPParser_Node_Expr_MethodCall(
                new \PHPParser_Node_Expr_Variable('controller'),
                'requireHttp'
            );
        }

        return $nodes;
    }

    /**
     * @param Route $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerIsRequiredHttps(Route $route)
    {
        $nodes = array();
        if($route->isRequireHttp()) {
            $nodes[] = new \PHPParser_Node_Expr_MethodCall(
                new \PHPParser_Node_Expr_Variable('controller'),
                'requireHttps'
            );
        }

        return $nodes;
    }

    /**
     * @param Route $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerBefore(Route $route)
    {
        $nodes = array();
        foreach($route->getBefore() as $before) {

            $callback = $before->getCallback();

            $matches = array();

            // controller as service callback
            if (preg_match('/^([^:]+):([^:]+)$/', $callback, $matches) === 1) {

                $callbackNode = $this->prepareControllerBeforeClosure(
                    $matches[1],
                    $matches[2]
                );
            } else {
                $callbackNode = new \PHPParser_Node_Scalar_String($callback);
            }

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

        return $nodes;
    }

    /**
     * @param string $serviceId
     * @param string $methodName
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
     * @param Route $route
     * @return \PHPParser_Node[]
     */
    protected function prepareControllerAfter(Route $route)
    {
        $nodes = array();
        foreach($route->getAfter() as $after) {

            $callback = $after->getCallback();

            $matches = array();

            // controller as service callback
            if (preg_match('/^([^:]+):([^:]+)$/', $callback, $matches) === 1) {

                $callbackNode = $this->prepareControllerAfterClosure(
                    $matches[1],
                    $matches[2]
                );
            } else {
                $callbackNode = new \PHPParser_Node_Scalar_String($callback);
            }

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

        return $nodes;
    }

    /**
     * @param string $serviceId
     * @param string $methodName
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
     * @param ControllerInfo $controllerInfo
     * @return \PHPParser_Node_Expr_MethodCall
     */
    protected function prepareControllersMountNode(ControllerInfo $controllerInfo)
    {
        $mount = '';

        $route = $controllerInfo->getAnnotationInfo()->getRoute();

        if(!is_null($route)) {
            $mount = $route->getMatch();
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