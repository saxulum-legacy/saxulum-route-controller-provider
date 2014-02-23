<?php

namespace Saxulum\RouteController\Manager;

use Saxulum\RouteController\Annotation\DI;
use Saxulum\RouteController\Helper\ControllerInfo;
use Saxulum\RouteController\Helper\MethodInfo;

class ServiceManager
{
    /**
     * @param ControllerInfo $controllerInfo
     * @return \PHPParser_Node[]
     */
    public function generateCode(ControllerInfo $controllerInfo)
    {
        $statements = array();
        $statements[] = $this->prepareConstructStatement($controllerInfo);

        foreach($controllerInfo->getMethodInfos() as $methodInfo) {
            $statement = $this->prepareMethodStatement($methodInfo);
            if(!is_null($statement)) {
                $statements[] = $statement;
            }
        }

        $statements[] = $this->prepareReturnStatement();

        return array($this->prepareNode($controllerInfo, $statements));
    }

    /**
     * @param ControllerInfo $controllerInfo
     * @return \PHPParser_Node_Expr_Assign
     */
    protected function prepareConstructStatement(ControllerInfo $controllerInfo)
    {
        return new \PHPParser_Node_Expr_Assign(
            new \PHPParser_Node_Expr_Variable('controller'),
            new \PHPParser_Node_Expr_New(
                new \PHPParser_Node_Name($controllerInfo->getNamespace()),
                $this->prepareConstructArguments(
                    $controllerInfo->getAnnotationInfo()->getDi()
                )
            )
        );
    }

    /**
     * @param DI $di
     * @return \PHPParser_Node_Arg[]
     */
    protected function prepareConstructArguments(DI $di = null)
    {
        if(is_null($di)) {
            return array();
        }

        $constructArguments = array();

        if($di->isInjectContainer()) {
            $constructArguments[] = new \PHPParser_Node_Arg(
                new \PHPParser_Node_Expr_Variable('app')
            );
        } else {
            foreach($di->getServiceIds() as $serviceId) {
                $constructArguments[] = new \PHPParser_Node_Arg(
                    new \PHPParser_Node_Expr_ArrayDimFetch(
                        new \PHPParser_Node_Expr_Variable('app'),
                        new \PHPParser_Node_Scalar_String($serviceId)
                    )
                );
            }
        }

        return $constructArguments;
    }

    /**
     * @param MethodInfo $methodInfo
     * @return null|\PHPParser_Node_Expr_MethodCall
     */
    protected function prepareMethodStatement(MethodInfo $methodInfo)
    {
        $di = $methodInfo->getAnnotationInfo()->getDi();

        if(is_null($di)) {
            return null;
        }

        return new \PHPParser_Node_Expr_MethodCall(
            new \PHPParser_Node_Expr_Variable('controller'),
            $methodInfo->getName(),
            $this->prepareMethodArguments($di)
        );
    }

    /**
     * @param DI $di
     * @return \PHPParser_Node_Arg[]
     */
    protected function prepareMethodArguments(DI $di)
    {
        if(is_null($di)) {
            return array();
        }

        $methodArguments = array();

        if($di->isInjectContainer()) {
            $methodArguments[] = new \PHPParser_Node_Arg(
                new \PHPParser_Node_Expr_Variable('app')
            );
        } else {
            foreach($di->getServiceIds() as $serviceId) {
                $methodArguments[] = new \PHPParser_Node_Arg(
                    new \PHPParser_Node_Expr_ArrayDimFetch(
                        new \PHPParser_Node_Expr_Variable('app'),
                        new \PHPParser_Node_Scalar_String($serviceId)
                    )
                );
            }
        }

        return $methodArguments;
    }

    /**
     * @return \PHPParser_Node_Stmt_Return
     */
    protected function prepareReturnStatement()
    {
        return new \PHPParser_Node_Stmt_Return(
            new \PHPParser_Node_Expr_Variable('controller')
        );
    }

    /**
     * @param ControllerInfo $controllerInfo
     * @param array $statements
     * @return \PHPParser_Node_Expr_Assign
     */
    protected function prepareNode(ControllerInfo $controllerInfo, array $statements)
    {
        return new \PHPParser_Node_Expr_Assign(
            new \PHPParser_Node_Expr_ArrayDimFetch(
                new \PHPParser_Node_Expr_Variable('app'),
                new \PHPParser_Node_Scalar_String($controllerInfo->getserviceId())
            ),
            new \PHPParser_Node_Expr_MethodCall(
                new \PHPParser_Node_Expr_Variable('app'),
                'share',
                array(
                    new \PHPParser_Node_Arg(
                        new \PHPParser_Node_Expr_Closure(
                            array(
                                'uses' => array(
                                    new \PHPParser_Node_Expr_ClosureUse('app')
                                ),
                                'stmts' => $statements
                            )
                        )
                    )
                )
            )
        );
    }
}