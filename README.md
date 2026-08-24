# generator-utils

Shared [nikic/php-parser](https://github.com/nikic/PHP-Parser) utilities for [OpenAPI Tools](https://github.com/php-openapi-tools) code generators.

![Continuous Integration](https://github.com/php-openapi-tools/generator-utils/workflows/Continuous%20Integration/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/openapi-tools/generator-utils/v/stable.png)](https://packagist.org/packages/openapi-tools/generator-utils)
[![Total Downloads](https://poser.pugx.org/openapi-tools/generator-utils/downloads.png)](https://packagist.org/packages/openapi-tools/generator-utils/stats)
[![License](https://poser.pugx.org/openapi-tools/generator-utils/license.png)](https://packagist.org/packages/openapi-tools/generator-utils)

## Installation

To install via [Composer](https://getcomposer.org/), use the command below, it will automatically detect the latest version and bind it with `^`.

```
composer require openapi-tools/generator-utils
```

This package is typically pulled in as a dependency of generator packages such as [`generator-schema`](https://github.com/php-openapi-tools/generator-schema) and [`generator-hydrator`](https://github.com/php-openapi-tools/generator-hydrator). You can also require it directly when building custom `FileGenerator` implementations.

## Requirements

- PHP `^8.4`
- [`openapi-tools/representation`](https://github.com/php-openapi-tools/representation) for type resolution helpers
- [`openapi-tools/utils`](https://github.com/php-openapi-tools/utils) for `File`, `ClassString`, and related value objects
- [`nikic/php-parser`](https://github.com/nikic/PHP-Parser) for AST construction and pretty-printing

## Components

| Class | Purpose |
| --- | --- |
| `FileStringyfier` | Pretty-print AST `File` contents with `strict_types` |
| `Builder\DocBlockBuilder` | Format PHPDoc blocks from line arrays |
| `Builder\ExpressionBuilder` | Common AST expressions (`var`, `thisMethod`, `identical`, `andAll`, …) |
| `Builder\StatementBuilder` | Common AST statements (`assign`, `yieldMapOverIterable`, …) |
| `Builder\MatchBuilder` | Build `match` expressions on variables or class name strings |
| `Builder\HydrationBuilder` | Generate `$receiver->hydrate($class, $data)`-style calls |
| `Builder\NamespaceBuilder` | Wrap class nodes in namespace `File` objects |
| `Type\UnionTypeUtils` | Resolve OpenAPI union types to PHP type strings |
| `Type\PropertyTypeResolver` | Resolve schema property types and docblock lines |
| `Type\ResolvedPropertyType` | Value object with type hints, nullable prefix, and docblock line |
| `Type\DocBlockTag` | Select `@property` or `@param` docblock formatting |

## Usage

All builder classes expose static factory methods that return `PhpParser\Node` instances. Pass them to a `PhpParser\PrettyPrinter\Standard` (or another pretty printer) to inspect or embed generated code, then wrap the result in an [`openapi-tools/utils`](https://github.com/php-openapi-tools/utils) `File` for writing.

The examples below use `$printer = new PhpParser\PrettyPrinter\Standard()` to show the PHP that each method produces.

### `FileStringyfier`

#### `toString(File $file): string`

Converts a `File` to its on-disk representation. String contents are returned unchanged (with a trailing newline). AST contents are pretty-printed and prefixed with `declare(strict_types=1);`.

```php
use OpenAPITools\Generator\Utils\FileStringyfier;
use OpenAPITools\Utils\File;
use PhpParser\BuilderFactory;
use PhpParser\PrettyPrinter\Standard;

$builderFactory = new BuilderFactory();
$stringyfier    = new FileStringyfier(new Standard());

// AST-backed file
$astFile = new File(
    'src',
    'Example',
    $builderFactory->namespace('Vendor\\Api')
        ->addStmt($builderFactory->class('Example')->makeFinal()->getNode())
        ->getNode(),
    File::DO_NOT_LOAD_ON_WRITE,
);

$php = $stringyfier->toString($astFile);
// generates into
<<<'PHP'
<?php

declare (strict_types=1);
namespace Vendor\Api;

final class Example
{
}

PHP;

// String-backed file (for example generation state JSON)
$jsonFile = new File('', 'state.json', '{"specHash":""}', File::DO_NOT_LOAD_ON_WRITE);

$json = $stringyfier->toString($jsonFile);
// generates into
'{"specHash":""}' . PHP_EOL
```

### `Builder\DocBlockBuilder`

#### `fromLines(array $lines): string`

Builds a PHPDoc block from plain text lines. Each line is prefixed with ` * ` automatically.

```php
use OpenAPITools\Generator\Utils\Builder\DocBlockBuilder;

$docComment = DocBlockBuilder::fromLines([
    'A generated schema class.',
    '@property string $name',
    '@property int $count',
]);

// generates into
/**
 * A generated schema class.
 * @property string $name
 * @property int $count
 */

$builderFactory->method('__construct')->setDocComment($docComment);
```

#### `fromDocLines(array $lines): string`

Like `fromLines()`, but strips existing `/**` and `*/` markers from each line first. Useful when collecting lines that may already be partial docblocks.

```php
use OpenAPITools\Generator\Utils\Builder\DocBlockBuilder;
use OpenAPITools\Generator\Utils\Type\DocBlockTag;
use OpenAPITools\Generator\Utils\Type\PropertyTypeResolver;

$lines = [];
foreach ($schema->properties as $property) {
    $resolved = PropertyTypeResolver::resolve($property, DocBlockTag::Param);
    if ($resolved->docBlockLine !== '') {
        $lines[] = $resolved->docBlockLine;
    }
}

$constructor->setDocComment(DocBlockBuilder::fromDocLines([
    '/** @param string $name */',
    ...$lines,
]));
// generates into
/**
 *  @param string $name
 * @param ?array<\Vendor\Schema\Item> $items
 */
```

### `Builder\ExpressionBuilder`

Method arguments accept variable names as strings, `Expr` nodes, or `Arg` instances.

#### `var(string $name): Expr\Variable`

Creates a `$variable` reference.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$variable = ExpressionBuilder::var('payload');

$printer->prettyPrintExpr($variable);
// generates into
$payload
```

#### `thisProperty(string $name): Expr\PropertyFetch`

Creates a `$this->property` fetch.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$property = ExpressionBuilder::thisProperty('handler');

$printer->prettyPrintExpr($property);
// generates into
$this->handler
```

#### `thisMethod(string $method, array $arguments = []): Expr\MethodCall`

Creates a `$this->method(...)` call. String arguments are treated as variable names.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$call = ExpressionBuilder::thisMethod('resolve', ['headers', 'data']);

$printer->prettyPrintExpr($call);
// generates into
$this->resolve($headers, $data)
```

#### `methodCall(Expr $receiver, string $method, array $arguments = []): Expr\MethodCall`

Creates a method call on an arbitrary receiver expression.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$call = ExpressionBuilder::methodCall(
    ExpressionBuilder::thisMethod('getObjectMapperUsers'),
    'hydrateObject',
    ['className', 'payload'],
);

$printer->prettyPrintExpr($call);
// generates into
$this->getObjectMapperUsers()->hydrateObject($className, $payload)
```

#### `classConstant(string $className, string $constant = 'class'): Expr\ClassConstFetch`

Creates a class constant fetch, defaulting to `::class`.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$class = ExpressionBuilder::classConstant('\\Vendor\\Schema\\User');

$printer->prettyPrintExpr($class);
// generates into
\Vendor\Schema\User::class
```

#### `objectClassConstant(string $variable, string $constant = 'class'): Expr\ClassConstFetch`

Creates a `$variable::class` fetch for runtime class name resolution.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$className = ExpressionBuilder::objectClassConstant('object');

$printer->prettyPrintExpr($className);
// generates into
$object::class
```

#### `arrayFetch(string|Expr $array, string $key): Expr\ArrayDimFetch`

Creates an array offset fetch with a string literal key. Pass a variable name as a string or an `Expr` as the array.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$fetch = ExpressionBuilder::arrayFetch('data', 'id');

$printer->prettyPrintExpr($fetch);
// generates into
$data['id']
```

#### `arrayFetchKey(string|Expr $array, Expr $key): Expr\ArrayDimFetch`

Creates an array offset fetch with a dynamic key expression.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$fetch = ExpressionBuilder::arrayFetchKey('data', ExpressionBuilder::var('index'));

$printer->prettyPrintExpr($fetch);
// generates into
$data[$index]
```

#### `identical(Expr $left, Expr $right): Expr\BinaryOp\Identical`

Creates a strict equality comparison (`===`).

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$comparison = ExpressionBuilder::identical(
    ExpressionBuilder::var('status'),
    ExpressionBuilder::literalString('ok'),
);

$printer->prettyPrintExpr($comparison);
// generates into
$status === 'ok'
```

#### `andAll(array $conditions): Expr`

Combines two or more expressions with `&&`.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$condition = ExpressionBuilder::andAll([
    ExpressionBuilder::identical(ExpressionBuilder::var('a'), ExpressionBuilder::literalString('a')),
    ExpressionBuilder::identical(ExpressionBuilder::var('b'), ExpressionBuilder::literalString('b')),
]);

$printer->prettyPrintExpr($condition);
// generates into
$a === 'a' && $b === 'b'
```

#### `orAll(array $conditions): Expr`

Combines two or more expressions with `||`.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$condition = ExpressionBuilder::orAll([
    ExpressionBuilder::identical(ExpressionBuilder::var('a'), ExpressionBuilder::literalString('a')),
    ExpressionBuilder::identical(ExpressionBuilder::var('b'), ExpressionBuilder::literalString('b')),
]);

$printer->prettyPrintExpr($condition);
// generates into
$a === 'a' || $b === 'b'
```

#### `nullSafe(Expr $value, Expr $nonNull): Expr\Ternary`

Creates a null-coalescing-style ternary: `$value === null ? null : $nonNull`.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$ternary = ExpressionBuilder::nullSafe(
    ExpressionBuilder::var('value'),
    ExpressionBuilder::thisProperty('value'),
);

$printer->prettyPrintExpr($ternary);
// generates into
$value === null ? null : $this->value
```

#### `null(): Expr\ConstFetch`

Creates a `null` literal.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$printer->prettyPrintExpr(ExpressionBuilder::null());
// generates into
null
```

#### `true(): Expr\ConstFetch`

Creates a `true` literal.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$printer->prettyPrintExpr(ExpressionBuilder::true());
// generates into
true
```

#### `false(): Expr\ConstFetch`

Creates a `false` literal.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$printer->prettyPrintExpr(ExpressionBuilder::false());
// generates into
false
```

#### `literalString(string $value): Scalar\String_`

Creates a string literal.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$literal = ExpressionBuilder::literalString('missing');

$printer->prettyPrintExpr($literal);
// generates into
'missing'
```

#### `literalInt(int $value): Scalar\LNumber`

Creates an integer literal.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$literal = ExpressionBuilder::literalInt(42);

$printer->prettyPrintExpr($literal);
// generates into
42
```

#### `invoke(Expr $callable, array $arguments = []): Expr\FuncCall`

Invokes a callable stored in a variable.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$call = ExpressionBuilder::invoke(ExpressionBuilder::var('callback'), ['value']);

$printer->prettyPrintExpr($call);
// generates into
$callback($value)
```

#### `funcCall(string $function, array $arguments = []): Expr\FuncCall`

Creates a global function call.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$call = ExpressionBuilder::funcCall('strlen', ['value']);

$printer->prettyPrintExpr($call);
// generates into
strlen($value)
```

#### `newInstance(string $className, array $arguments = []): Expr\New_`

Creates a `new ClassName(...)` expression.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$instance = ExpressionBuilder::newInstance('\\EventSauce\\ObjectHydrator\\IterableList', [
    ExpressionBuilder::thisMethod('doHydrateObjects', ['className', 'payloads']),
]);

$printer->prettyPrintExpr($instance);
// generates into
new \EventSauce\ObjectHydrator\IterableList($this->doHydrateObjects($className, $payloads))
```

#### `newRuntimeException(string $message): Expr\New_`

Creates a `new \RuntimeException('message')` expression.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$exception = ExpressionBuilder::newRuntimeException('Unknown class');

$printer->prettyPrintExpr($exception);
// generates into
new \RuntimeException('Unknown class')
```

#### `throwRuntimeException(string $message): Stmt\Expression`

Creates a `throw new \RuntimeException('message');` statement.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$statement = ExpressionBuilder::throwRuntimeException('missing');

$printer->prettyPrint([$statement]);
// generates into
throw new \RuntimeException('missing');
```

#### `throwNew(string $className, array $arguments = []): Stmt\Expression`

Creates a `throw new ClassName(...);` statement.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$statement = ExpressionBuilder::throwNew('\\InvalidArgumentException', ['message']);

$printer->prettyPrint([$statement]);
// generates into
throw new \InvalidArgumentException($message);
```

#### `catchThrowable(string $throwableVariable, string $errorVariable): Stmt\Catch_`

Creates a catch block that assigns the caught throwable to a second variable.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$catch = ExpressionBuilder::catchThrowable('throwable', 'error');

$printer->prettyPrint([$catch]);
// generates into
catch (\Throwable $throwable) {
$error = $throwable;
}
```

#### `lazyInitIfNotInstance(string $propertyName, string $className, array $constructorArguments = []): Stmt\If_`

Creates an `if` block that lazily initializes a `$this->property` when it is not yet an instance of the given class.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$if = ExpressionBuilder::lazyInitIfNotInstance('mapper', '\\Vendor\\Mapper', ['config']);

$printer->prettyPrint([$if]);
// generates into
if ($this->mapper instanceof \Vendor\Mapper === false) {
$this->mapper = new \Vendor\Mapper($config);
}
```

#### `returnProperty(string $propertyName): Stmt\Return_`

Creates a `return $this->property;` statement.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

$return = ExpressionBuilder::returnProperty('mapper');

$printer->prettyPrint([$return]);
// generates into
return $this->mapper;
```

#### `args(array $arguments): array`

Normalizes a mixed argument list to `Arg` objects. Strings become variable references; `Expr` nodes are wrapped; existing `Arg` instances pass through unchanged.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;
use PhpParser\Node\Arg;

$args = ExpressionBuilder::args([
    new Arg(ExpressionBuilder::var('value')),
    'other',
]);

// $args[0] wraps $value, $args[1] wraps $other
```

### `Builder\StatementBuilder`

#### `assign(string|Expr $variable, Expr $value): Stmt\Expression`

Creates an assignment statement. The variable may be a name or an expression (for example `$this->mapper`).

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;
use OpenAPITools\Generator\Utils\Builder\StatementBuilder;

$assign = StatementBuilder::assign(
    'payload',
    ExpressionBuilder::thisMethod('resolve', ['data']),
);

$printer->prettyPrint([$assign]);
// generates into
$payload = $this->resolve($data);
```

#### `yieldMapOverIterable(string $iterableVariable, string $itemVariable, string $indexVariable, string $methodName, array $methodArguments = []): Stmt\Foreach_`

Creates a `foreach` that yields `$index => $this->method(...)` for each item. Used by hydrator generators for batch operations.

```php
use OpenAPITools\Generator\Utils\Builder\StatementBuilder;

$foreach = StatementBuilder::yieldMapOverIterable(
    'payloads',
    'payload',
    'index',
    'hydrateObject',
    ['className', 'payload'],
);

$printer->prettyPrint([$foreach]);
// generates into
foreach ($payloads as $index => $payload) {
yield $index => $this->hydrateObject($className, $payload);
}
```

#### `tryReturnIgnoringThrowable(Stmt\Return_ $return): Stmt\TryCatch`

Wraps a return statement in a try/catch that swallows any `\Throwable`.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;
use OpenAPITools\Generator\Utils\Builder\StatementBuilder;
use PhpParser\Node\Stmt\Return_;

$tryCatch = StatementBuilder::tryReturnIgnoringThrowable(
    new Return_(ExpressionBuilder::var('value')),
);

$printer->prettyPrint([$tryCatch]);
// generates into
try {
return $value;
} catch (\Throwable) {
}
```

#### `throwVariable(string $variable): Stmt\Expression`

Creates a `throw $variable;` statement.

```php
use OpenAPITools\Generator\Utils\Builder\StatementBuilder;

$throw = StatementBuilder::throwVariable('error');

$printer->prettyPrint([$throw]);
// generates into
throw $error;
```

#### `returnMethodCall(string $variable, string $method, array $arguments = []): Stmt\Return_`

Creates a `return $variable->method(...);` statement.

```php
use OpenAPITools\Generator\Utils\Builder\StatementBuilder;

$return = StatementBuilder::returnMethodCall('receiver', 'run', ['input']);

$printer->prettyPrint([$return]);
// generates into
return $receiver->run($input);
```

### `Builder\MatchBuilder`

#### `onVariable(string $variableName, array $arms, Expr ...$default): Expr\Match_`

Creates a `match` on a variable. Each arm has a `conditions` list and a `body` expression. Pass a default body as a variadic argument after the arms.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;
use OpenAPITools\Generator\Utils\Builder\MatchBuilder;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

$match = MatchBuilder::onVariable('status', [
    [
        'conditions' => [new Scalar\String_('ok')],
        'body' => ExpressionBuilder::thisProperty('handler'),
    ],
], ExpressionBuilder::throwRuntimeException('Unknown status'));

$printer->prettyPrintExpr($match);
// generates into
match ($status) {
'ok' => $this->handler,
default => throw new \RuntimeException('Unknown status'),
}
```

#### `onClassNameStrings(string $variableName, array $arms, Expr ...$default): Expr\Match_`

Creates a `match` on a class name string. Each arm maps one or more class name strings to a body expression. This is the pattern used by hydrator dispatch.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;
use OpenAPITools\Generator\Utils\Builder\MatchBuilder;

$match = MatchBuilder::onClassNameStrings('className', [
    [
        'classNames' => ['\\Vendor\\Schema\\Foo', '\\Vendor\\Schema\\Bar'],
        'body' => ExpressionBuilder::methodCall(
            ExpressionBuilder::thisMethod('getObjectMapperUsers'),
            'hydrateObject',
            ['className', 'payload'],
        ),
    ],
], ExpressionBuilder::throwRuntimeException('Unknown class'));

$printer->prettyPrintExpr($match);
// generates into
match ($className) {
'\\Vendor\\Schema\\Foo', '\\Vendor\\Schema\\Bar' => $this->getObjectMapperUsers()->hydrateObject($className, $payload),
default => throw new \RuntimeException('Unknown class'),
}
```

### `Builder\HydrationBuilder`

#### `hydrateCall(string $className, Expr $data, Expr $receiver, string $method = 'hydrate'): Expr\MethodCall`

Creates a hydrator method call that passes a class constant and data payload to a receiver.

```php
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;
use OpenAPITools\Generator\Utils\Builder\HydrationBuilder;

$call = HydrationBuilder::hydrateCall(
    '\\Vendor\\Schema\\User',
    ExpressionBuilder::var('data'),
    ExpressionBuilder::var('hydrator'),
);

$printer->prettyPrintExpr($call);
// generates into
$hydrator->hydrate(\Vendor\Schema\User::class, $data)

$fromArray = HydrationBuilder::hydrateCall(
    '\\Vendor\\Schema\\User',
    ExpressionBuilder::var('data'),
    ExpressionBuilder::var('hydrator'),
    'fromArray',
);

$printer->prettyPrintExpr($fromArray);
// generates into
$hydrator->fromArray(\Vendor\Schema\User::class, $data)
```

### `Builder\NamespaceBuilder`

#### `namespaceNode(string $namespace, Node ...$statements): Node\Stmt\Namespace_`

Wraps one or more AST statements in a namespace node.

```php
use OpenAPITools\Generator\Utils\Builder\NamespaceBuilder;
use PhpParser\BuilderFactory;
use PhpParser\PrettyPrinter\Standard;

$builderFactory = new BuilderFactory();
$namespaceBuilder = new NamespaceBuilder($builderFactory);

$classNode = $builderFactory->class('Example')->makeFinal()->getNode();
$namespace = $namespaceBuilder->namespaceNode('Vendor\\Api', $classNode);

$printer = new Standard();
$printer->prettyPrint([$namespace]);
// generates into
namespace Vendor\Api;

final class Example
{
}
```

#### `classFile(string $pathPrefix, ClassString $className, Node $classNode, bool $loadOnWrite = File::DO_LOAD_ON_WRITE): File`

Builds a `File` with the correct path prefix, FQCN, namespace-wrapped class node, and load-on-write flag.

```php
use OpenAPITools\Generator\Utils\Builder\NamespaceBuilder;
use OpenAPITools\Utils\ClassString;
use OpenAPITools\Utils\File;
use OpenAPITools\Utils\Namespace_;
use PhpParser\BuilderFactory;

$builderFactory = new BuilderFactory();
$classString    = ClassString::factory(
    new Namespace_('Vendor\\Api', 'Vendor\\Tests\\Api'),
    'Schema\\User',
);
$classNode      = $builderFactory->class($classString->className)->makeFinal()->getNode();

$file = new NamespaceBuilder($builderFactory)->classFile(
    'src',
    $classString,
    $classNode,
    File::DO_LOAD_ON_WRITE,
);

// generates into
$file->pathPrefix === 'src'
$file->fqcn === 'Schema\\User'
$file->loadOnWrite === true
$file->contents is a namespace node wrapping the class
```

### `Type\UnionTypeUtils`

Type helpers operate on [`openapi-tools/representation`](https://github.com/php-openapi-tools/representation) `Namespaced\Property\Type` objects.

#### `buildUnionType(Namespaced\Property\Type $type): string`

Flattens union type nodes into a deduplicated PHP type string.

```php
use OpenAPITools\Generator\Utils\Type\UnionTypeUtils;
use OpenAPITools\Representation\Namespaced\Property\Type;

$typeString = UnionTypeUtils::buildUnionType(new Type('union', [
    new Type('string', 'string', null, [], false),
    new Type('string', 'int', null, [], false),
    new Type('object', $schema, null, [], false),
], false));

// generates into
'string|int|\\Vendor\\Schema\\User'
```

#### `getUnionTypeSchemas(Namespaced\Property\Type $type): iterable`

Yields every `Namespaced\Schema` referenced in a union, including nested unions. Generators use this to emit cast helpers for schema-backed union properties.

```php
use OpenAPITools\Generator\Utils\Type\UnionTypeUtils;

$schemaClasses = [...UnionTypeUtils::getUnionTypeSchemas($property->type)];

foreach ($schemaClasses as $schema) {
    yield from SingleCastUnionToType::generate(
        $builderFactory,
        $pathPrefix,
        $castToUnionToType,
        $schema,
    );
}
```

### `Type\PropertyTypeResolver`

#### `resolve(Namespaced\Property $property, DocBlockTag $docBlockTag = DocBlockTag::Property): ResolvedPropertyType`

Resolves a representation property to type hints, a nullable prefix, and an optional docblock line.

```php
use OpenAPITools\Generator\Utils\Type\DocBlockTag;
use OpenAPITools\Generator\Utils\Type\PropertyTypeResolver;

// Promoted property / class docblock (default)
$resolved = PropertyTypeResolver::resolve($property);

$resolved->typeHints;
// generates into
['string|int']
$resolved->nullablePrefix;
// generates into
'null|'
$resolved->docBlockLine;
// generates into
'@property null|string|int $value'
$resolved->typeHint();
// generates into
'null|string|int'

// Constructor parameter docblock
$param = PropertyTypeResolver::resolve($property, DocBlockTag::Param);

$param->docBlockLine;
// generates into
'@param ?array<\\Vendor\\Schema\\Item> $items'
$param->typeHint();
// generates into
'?array'

// Apply to a generated constructor parameter
$constructorParam = $builderFactory->param($property->name)->makePublic();
if ($param->docBlockLine !== '') {
    $constructDocBlock[] = $param->docBlockLine;
}
if ($param->typeHint() !== '') {
    $constructorParam->setType($param->typeHint());
}
```

Use `DocBlockTag::Property` for promoted properties and class docblocks. Use `DocBlockTag::Param` for constructor parameters. Array properties receive generic docblock annotations when the element type can be resolved, for example `array<\\Vendor\\Schema\\Item>` or `array<string|int>`.

### `Type\ResolvedPropertyType`

#### `typeHint(): string`

Returns the combined nullable prefix and type hints as a single PHP type string suitable for `Param::setType()`.

```php
use OpenAPITools\Generator\Utils\Type\PropertyTypeResolver;

$resolved = PropertyTypeResolver::resolve($property);

if ($resolved->typeHint() !== '') {
    $constructorParam->setType($resolved->typeHint());
}

// Nullable union: 'null|string|int'
// Nullable single:  '?\\Vendor\\Schema\\User'
// Non-nullable:     'string'
// Unknown type:     '' (empty string, skip setType)
```

### `Type\DocBlockTag`

Selects which PHPDoc tag `PropertyTypeResolver::resolve()` emits:

- `DocBlockTag::Property` — `@property` lines for class and promoted property documentation
- `DocBlockTag::Param` — `@param` lines for constructor parameter documentation

```php
use OpenAPITools\Generator\Utils\Type\DocBlockTag;
use OpenAPITools\Generator\Utils\Type\PropertyTypeResolver;

$propertyLine = PropertyTypeResolver::resolve($property, DocBlockTag::Property);
$paramLine    = PropertyTypeResolver::resolve($property, DocBlockTag::Param);
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT)

Copyright (c) 2026 Cees-Jan Kiewiet

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
