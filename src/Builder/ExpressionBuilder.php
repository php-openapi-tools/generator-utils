<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Utils\Builder;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;

use function array_map;
use function array_reduce;
use function array_slice;
use function is_string;

/** @api */
final class ExpressionBuilder
{
    public static function var(string $name): Expr\Variable
    {
        return new Expr\Variable($name);
    }

    public static function thisProperty(string $name): Expr\PropertyFetch
    {
        return new Expr\PropertyFetch(self::var('this'), $name);
    }

    /** @param list<Arg|Expr|string> $arguments */
    public static function thisMethod(string $method, array $arguments = []): Expr\MethodCall
    {
        return new Expr\MethodCall(self::var('this'), $method, self::args($arguments));
    }

    /** @param list<Arg|Expr|string> $arguments */
    public static function methodCall(Expr $receiver, string $method, array $arguments = []): Expr\MethodCall
    {
        return new Expr\MethodCall($receiver, $method, self::args($arguments));
    }

    public static function classConstant(string $className, string $constant = 'class'): Expr\ClassConstFetch
    {
        return new Expr\ClassConstFetch(new Name($className), $constant);
    }

    public static function objectClassConstant(string $variable, string $constant = 'class'): Expr\ClassConstFetch
    {
        return new Expr\ClassConstFetch(self::var($variable), $constant);
    }

    public static function arrayFetch(string|Expr $array, string $key): Expr\ArrayDimFetch
    {
        $arrayExpr = is_string($array) ? self::var($array) : $array;

        return new Expr\ArrayDimFetch($arrayExpr, self::literalString($key));
    }

    public static function arrayFetchKey(string|Expr $array, Expr $key): Expr\ArrayDimFetch
    {
        $arrayExpr = is_string($array) ? self::var($array) : $array;

        return new Expr\ArrayDimFetch($arrayExpr, $key);
    }

    public static function identical(Expr $left, Expr $right): Expr\BinaryOp\Identical
    {
        return new Expr\BinaryOp\Identical($left, $right);
    }

    /** @param non-empty-list<Expr> $conditions */
    public static function andAll(array $conditions): Expr
    {
        return array_reduce(
            array_slice($conditions, 1),
            static fn (Expr $carry, Expr $condition): Expr => new Expr\BinaryOp\BooleanAnd($carry, $condition),
            $conditions[0],
        );
    }

    /** @param non-empty-list<Expr> $conditions */
    public static function orAll(array $conditions): Expr
    {
        return array_reduce(
            array_slice($conditions, 1),
            static fn (Expr $carry, Expr $condition): Expr => new Expr\BinaryOp\BooleanOr($carry, $condition),
            $conditions[0],
        );
    }

    public static function nullSafe(Expr $value, Expr $nonNull): Expr\Ternary
    {
        return new Expr\Ternary(
            self::identical($value, self::null()),
            self::null(),
            $nonNull,
        );
    }

    public static function null(): Expr\ConstFetch
    {
        return new Expr\ConstFetch(new Name('null'));
    }

    public static function true(): Expr\ConstFetch
    {
        return new Expr\ConstFetch(new Name('true'));
    }

    public static function false(): Expr\ConstFetch
    {
        return new Expr\ConstFetch(new Name('false'));
    }

    public static function literalString(string $value): Scalar\String_
    {
        return new Scalar\String_($value);
    }

    public static function literalInt(int $value): Scalar\LNumber
    {
        return new Scalar\LNumber($value);
    }

    /** @param list<Arg|Expr|string> $arguments */
    public static function invoke(Expr $callable, array $arguments = []): Expr\FuncCall
    {
        return new Expr\FuncCall($callable, self::args($arguments));
    }

    /** @param list<Arg|Expr|string> $arguments */
    public static function funcCall(string $function, array $arguments = []): Expr\FuncCall
    {
        return new Expr\FuncCall(new Name($function), self::args($arguments));
    }

    /** @param list<Arg|Expr|string> $arguments */
    public static function newInstance(string $className, array $arguments = []): Expr\New_
    {
        return new Expr\New_(new Name($className), self::args($arguments));
    }

    public static function newRuntimeException(string $message): Expr\New_
    {
        return self::newInstance('\\RuntimeException', [self::literalString($message)]);
    }

    public static function throwRuntimeException(string $message): Stmt\Expression
    {
        return new Stmt\Expression(new Expr\Throw_(self::newRuntimeException($message)));
    }

    /** @param list<Arg|Expr|string> $arguments */
    public static function throwNew(string $className, array $arguments = []): Stmt\Expression
    {
        return new Stmt\Expression(new Expr\Throw_(self::newInstance($className, $arguments)));
    }

    public static function catchThrowable(string $throwableVariable, string $errorVariable): Stmt\Catch_
    {
        return new Stmt\Catch_(
            [new Name('\\Throwable')],
            self::var($throwableVariable),
            [
                StatementBuilder::assign($errorVariable, self::var($throwableVariable)),
            ],
        );
    }

    /** @param list<Arg> $constructorArguments */
    public static function lazyInitIfNotInstance(string $propertyName, string $className, array $constructorArguments = []): Stmt\If_
    {
        return new Stmt\If_(
            self::identical(
                new Expr\Instanceof_(self::thisProperty($propertyName), new Name($className)),
                self::false(),
            ),
            [
                'stmts' => [
                    StatementBuilder::assign(
                        self::thisProperty($propertyName),
                        self::newInstance($className, $constructorArguments),
                    ),
                ],
            ],
        );
    }

    public static function returnProperty(string $propertyName): Stmt\Return_
    {
        return new Stmt\Return_(self::thisProperty($propertyName));
    }

    /**
     * @param list<Arg|Expr|string> $arguments
     *
     * @return list<Arg>
     */
    public static function args(array $arguments): array
    {
        return array_map(
            static fn (Arg|Expr|string $argument): Arg => $argument instanceof Arg
                ? $argument
                : new Arg($argument instanceof Expr ? $argument : self::var($argument)),
            $arguments,
        );
    }
}
