<?php

namespace Saxulum\RouteController\Manager;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ClosureUse;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String;
use PhpParser\Node\Stmt\Return_;
use Saxulum\RouteController\Annotation\DI;
use Saxulum\AnnotationManager\Helper\ClassInfo;
use Saxulum\AnnotationManager\Helper\MethodInfo;

class ServiceManager
{
    /**
     * @param  ClassInfo $classInfo
     * @return Assign[]
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
     * @param  ClassInfo $classInfo
     * @return Assign
     */
    protected function prepareConstructStatement(ClassInfo $classInfo)
    {
        $di = $classInfo->getFirstAnnotationInstanceof(
            'Saxulum\\RouteController\\Annotation\\DI'
        );

        /** @var DI $di */

        return new Assign(
            new Variable('controller'),
            new New_(
                new Name($classInfo->getName()),
                $this->prepareArguments($di)
            )
        );
    }

    /**
     * @param  DI    $di
     * @return Arg[]
     */
    protected function prepareArguments(DI $di = null)
    {
        if (is_null($di)) {
            return array();
        }

        $arguments = array();

        if ($di->injectContainer) {
            $arguments[] = new Arg(
                new Variable('app')
            );
        } else {
            foreach ($di->serviceIds as $serviceId) {
                $arguments[] = new Arg(
                    new ArrayDimFetch(
                        new Variable('app'),
                        new String($serviceId)
                    )
                );
            }
        }

        return $arguments;
    }

    /**
     * @param  MethodInfo      $methodInfo
     * @return null|MethodCall
     */
    protected function prepareMethodStatement(MethodInfo $methodInfo)
    {
        $di = $methodInfo->getFirstAnnotationInstanceof(
            'Saxulum\\RouteController\\Annotation\\DI'
        );

        /** @var DI $di */

        if (is_null($di)) {
            return null;
        }

        return new MethodCall(
            new Variable('controller'),
            $methodInfo->getName(),
            $this->prepareArguments($di)
        );
    }

    /**
     * @return Return_
     */
    protected function prepareReturnStatement()
    {
        return new Return_(
            new Variable('controller')
        );
    }

    /**
     * @param  ClassInfo $classInfo
     * @param  array     $statements
     * @return Assign
     */
    protected function prepareNode(ClassInfo $classInfo, array $statements)
    {
        return new Assign(
            new ArrayDimFetch(
                new Variable('app'),
                new String($classInfo->getServiceId())
            ),
            new MethodCall(
                new Variable('app'),
                'share',
                array(
                    new Arg(
                        new Closure(
                            array(
                                'uses' => array(
                                    new ClosureUse('app')
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
