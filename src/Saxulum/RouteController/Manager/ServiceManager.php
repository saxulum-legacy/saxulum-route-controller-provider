<?php

namespace Saxulum\RouteController\Manager;

use Saxulum\RouteController\Annotation\DI;
use Saxulum\RouteController\Helper\ClassInfo;
use Saxulum\RouteController\Helper\MethodInfo;

class ServiceManager
{
    /**
     * @param  ClassInfo         $classInfo
     * @return \PHPParser_Node[]
     */
    public function generateCode(ClassInfo $classInfo)
    {
        $statements = array();
        $statements[] = $this->prepareConstructStatement($classInfo);

        foreach ($classInfo->getMethodInfos() as $methodInfo) {
            $statement = $this->prepareMethodStatement($methodInfo);
            if (!is_null($statement)) {
                $statements[] = $statement;
            }
        }

        $statements[] = $this->prepareReturnStatement();

        return array($this->prepareNode($classInfo, $statements));
    }

    /**
     * @param  ClassInfo                   $classInfo
     * @return \PHPParser_Node_Expr_Assign
     */
    protected function prepareConstructStatement(ClassInfo $classInfo)
    {
        return new \PHPParser_Node_Expr_Assign(
            new \PHPParser_Node_Expr_Variable('controller'),
            new \PHPParser_Node_Expr_New(
                new \PHPParser_Node_Name($classInfo->getName()),
                $this->prepareConstructArguments(
                    $classInfo->getFirstAnnotationInstanceof(
                        'Saxulum\\RouteController\\Annotation\\DI'
                    )
                )
            )
        );
    }

    /**
     * @param  DI                    $di
     * @return \PHPParser_Node_Arg[]
     */
    protected function prepareConstructArguments(DI $di = null)
    {
        if (is_null($di)) {
            return array();
        }

        $constructArguments = array();

        if ($di->injectContainer) {
            $constructArguments[] = new \PHPParser_Node_Arg(
                new \PHPParser_Node_Expr_Variable('app')
            );
        } else {
            foreach ($di->serviceIds as $serviceId) {
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
     * @param  MethodInfo                           $methodInfo
     * @return null|\PHPParser_Node_Expr_MethodCall
     */
    protected function prepareMethodStatement(MethodInfo $methodInfo)
    {
        $di = $methodInfo->getFirstAnnotationInstanceof(
            'Saxulum\\RouteController\\Annotation\\DI'
        );

        if (is_null($di)) {
            return null;
        }

        return new \PHPParser_Node_Expr_MethodCall(
            new \PHPParser_Node_Expr_Variable('controller'),
            $methodInfo->getName(),
            $this->prepareMethodArguments($di)
        );
    }

    /**
     * @param  DI                    $di
     * @return \PHPParser_Node_Arg[]
     */
    protected function prepareMethodArguments(DI $di)
    {
        if (is_null($di)) {
            return array();
        }

        $methodArguments = array();

        if ($di->injectContainer) {
            $methodArguments[] = new \PHPParser_Node_Arg(
                new \PHPParser_Node_Expr_Variable('app')
            );
        } else {
            foreach ($di->serviceIds as $serviceId) {
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
     * @param  ClassInfo                   $classInfo
     * @param  array                       $statements
     * @return \PHPParser_Node_Expr_Assign
     */
    protected function prepareNode(ClassInfo $classInfo, array $statements)
    {
        return new \PHPParser_Node_Expr_Assign(
            new \PHPParser_Node_Expr_ArrayDimFetch(
                new \PHPParser_Node_Expr_Variable('app'),
                new \PHPParser_Node_Scalar_String($classInfo->getServiceId())
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
