# generator-utils

Shared [nikic/php-parser](https://github.com/nikic/PHP-Parser) utilities for OpenAPI Tools code generators.

## Components

| Class | Purpose |
| --- | --- |
| `FileStringyfier` | Pretty-print AST `File` contents with `strict_types` |
| `Builder\DocBlockBuilder` | Format PHPDoc blocks from line arrays |
| `Builder\ExpressionBuilder` | Common AST expressions (`var`, `thisMethod`, `identical`, `andAll`, …) |
| `Builder\StatementBuilder` | Common AST statements (`assign`, `yieldMapOverIterable`, …) |
| `Builder\MatchBuilder` | Build `match` expressions on variables or class name strings |
| `Builder\HydrationBuilder` | Generate `$this->hydrate($class, $data)`-style calls |
| `Builder\NamespaceBuilder` | Wrap class nodes in namespace `File` objects |
| `Type\UnionTypeUtils` | Resolve OpenAPI union types to PHP type strings |
| `Type\PropertyTypeResolver` | Resolve schema property types and docblock lines |

## Usage

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;
use OpenAPITools\Generator\Utils\Builder\MatchBuilder;
use OpenAPITools\Generator\Utils\Builder\StatementBuilder;
use OpenAPITools\Generator\Utils\FileStringyfier;
use PhpParser\PrettyPrinter\Standard;

$methodBody = [
    StatementBuilder::assign('payload', ExpressionBuilder::thisMethod('resolve', ['headers', 'data'])),
    new Stmt\Return_(ExpressionBuilder::thisProperty('handler')),
];

$match = MatchBuilder::onClassNameStrings('className', [
    ['classNames' => ['\\Vendor\\Schema\\Foo'], 'body' => ExpressionBuilder::thisMethod('hydrateFoo', ['data'])],
]);

$php = new FileStringyfier(new Standard())->toString($file);
```
