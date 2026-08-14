<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Utils\Builder;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

use function array_map;

/** @api */
final class MatchBuilder
{
    /** @param list<array{conditions: list<Scalar\String_|Expr\ClassConstFetch>, body: Expr}> $arms */
    public static function onVariable(string $variableName, array $arms, Expr ...$default): Expr\Match_
    {
        $matchArms = [];

        foreach ($arms as $arm) {
            $matchArms[] = new Node\MatchArm($arm['conditions'], $arm['body']);
        }

        if ($default !== []) {
            $matchArms[] = new Node\MatchArm(null, $default[0]);
        }

        return new Expr\Match_(ExpressionBuilder::var($variableName), $matchArms);
    }

    /** @param list<array{classNames: list<string>, body: Expr}> $arms */
    public static function onClassNameStrings(string $variableName, array $arms, Expr ...$default): Expr\Match_
    {
        $matchArms = [];

        foreach ($arms as $arm) {
            $matchArms[] = new Node\MatchArm(
                array_map(
                    static fn (string $className): Scalar\String_ => new Scalar\String_($className),
                    $arm['classNames'],
                ),
                $arm['body'],
            );
        }

        if ($default !== []) {
            $matchArms[] = new Node\MatchArm(null, $default[0]);
        }

        return new Expr\Match_(ExpressionBuilder::var($variableName), $matchArms);
    }
}
