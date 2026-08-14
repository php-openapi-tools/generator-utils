<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Utils\Builder;

use PhpParser\Node\Expr;

/** @api */
final class HydrationBuilder
{
    public static function hydrateCall(
        string $className,
        Expr $data,
        Expr $receiver,
        string $method = 'hydrate',
    ): Expr\MethodCall {
        return ExpressionBuilder::methodCall(
            $receiver,
            $method,
            [
                ExpressionBuilder::classConstant($className),
                $data,
            ],
        );
    }
}
