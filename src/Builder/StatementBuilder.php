<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Utils\Builder;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;

use function is_string;

/** @api */
final class StatementBuilder
{
    public static function assign(string|Expr $variable, Expr $value): Stmt\Expression
    {
        $variableExpr = is_string($variable) ? ExpressionBuilder::var($variable) : $variable;

        return new Stmt\Expression(new Expr\Assign($variableExpr, $value));
    }

    /** @param list<Arg|Expr|string> $methodArguments */
    public static function yieldMapOverIterable(
        string $iterableVariable,
        string $itemVariable,
        string $indexVariable,
        string $methodName,
        array $methodArguments = [],
    ): Stmt\Foreach_ {
        return new Stmt\Foreach_(
            ExpressionBuilder::var($iterableVariable),
            ExpressionBuilder::var($itemVariable),
            [
                'keyVar' => ExpressionBuilder::var($indexVariable),
                'stmts' => [
                    new Stmt\Expression(
                        new Expr\Yield_(
                            ExpressionBuilder::thisMethod($methodName, $methodArguments),
                            ExpressionBuilder::var($indexVariable),
                        ),
                    ),
                ],
            ],
        );
    }

    public static function tryReturnIgnoringThrowable(Stmt\Return_ $return): Stmt\TryCatch
    {
        return new Stmt\TryCatch(
            [$return],
            [
                new Stmt\Catch_([new Name('\\Throwable')]),
            ],
        );
    }

    public static function throwVariable(string $variable): Stmt\Expression
    {
        return new Stmt\Expression(new Expr\Throw_(ExpressionBuilder::var($variable)));
    }

    /** @param list<Arg|Expr|string> $arguments */
    public static function returnMethodCall(string $variable, string $method, array $arguments = []): Stmt\Return_
    {
        return new Stmt\Return_(
            ExpressionBuilder::methodCall(ExpressionBuilder::var($variable), $method, $arguments),
        );
    }
}
