<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Generator\Utils;

use OpenAPITools\Generator\Utils\Builder\DocBlockBuilder;
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;
use OpenAPITools\Generator\Utils\Builder\MatchBuilder;
use OpenAPITools\Generator\Utils\Builder\NamespaceBuilder;
use OpenAPITools\Generator\Utils\Builder\StatementBuilder;
use OpenAPITools\Utils\ClassString;
use OpenAPITools\Utils\Namespace_;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

final class BuilderTest extends TestCase
{
    private Standard $printer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->printer = new Standard();
    }

    #[Test]
    public function docBlockBuilderFormatsLines(): void
    {
        self::assertSame(
            "/**\n * @property string \$name\n * @property int \$count\n */",
            DocBlockBuilder::fromLines(['@property string $name', '@property int $count']),
        );
    }

    #[Test]
    public function namespaceBuilderCreatesClassFile(): void
    {
        $builderFactory = new BuilderFactory();
        $classString    = ClassString::factory(new Namespace_('Vendor\\Package', 'Vendor\\Package\\Tests'), 'Internal\\Example');
        $file           = new NamespaceBuilder($builderFactory)->classFile(
            'src',
            $classString,
            $builderFactory->class($classString->className)->makeFinal()->getNode(),
        );

        self::assertSame('src', $file->pathPrefix);
        self::assertSame('Internal\\Example', $file->fqcn);
        self::assertInstanceOf(Node::class, $file->contents);
        self::assertStringContainsString(
            'namespace Vendor\\Package\\Internal;',
            $this->printer->prettyPrint([$file->contents]),
        );
        self::assertStringContainsString('final class Example', $this->printer->prettyPrint([$file->contents]));
    }

    #[Test]
    public function expressionBuilderCreatesLazyInit(): void
    {
        $if = ExpressionBuilder::lazyInitIfNotInstance('mapper', '\\Vendor\\Mapper');

        self::assertStringContainsString(
            '$this->mapper = new \\Vendor\\Mapper',
            $this->printer->prettyPrint([$if]),
        );
    }

    #[Test]
    public function expressionBuilderCreatesAccessors(): void
    {
        self::assertSame('$this->mapper', $this->printer->prettyPrintExpr(ExpressionBuilder::thisProperty('mapper')));
        self::assertSame('$this->run($input)', $this->printer->prettyPrintExpr(ExpressionBuilder::thisMethod('run', ['input'])));
        self::assertSame('\\Vendor\\Foo::class', $this->printer->prettyPrintExpr(ExpressionBuilder::classConstant('\\Vendor\\Foo')));
        self::assertSame('$data[\'id\']', $this->printer->prettyPrintExpr(ExpressionBuilder::arrayFetch('data', 'id')));
    }

    #[Test]
    public function expressionBuilderCombinesConditions(): void
    {
        $left  = ExpressionBuilder::literalString('a');
        $right = ExpressionBuilder::literalString('b');

        self::assertSame(
            '\'a\' === \'a\' && \'b\' === \'b\'',
            $this->printer->prettyPrintExpr(ExpressionBuilder::andAll([
                ExpressionBuilder::identical($left, $left),
                ExpressionBuilder::identical($right, $right),
            ])),
        );
        self::assertSame(
            '\'a\' === \'a\' || \'b\' === \'b\'',
            $this->printer->prettyPrintExpr(ExpressionBuilder::orAll([
                ExpressionBuilder::identical($left, $left),
                ExpressionBuilder::identical($right, $right),
            ])),
        );
    }

    #[Test]
    public function statementBuilderCreatesAssignAndYieldForeach(): void
    {
        self::assertSame(
            '$value = \'test\';',
            $this->printer->prettyPrint([StatementBuilder::assign('value', ExpressionBuilder::literalString('test'))]),
        );
        self::assertStringContainsString(
            'foreach ($items as $index => $item) {',
            $this->printer->prettyPrint([StatementBuilder::yieldMapOverIterable('items', 'item', 'index', 'map', ['item'])]),
        );
        self::assertStringContainsString(
            'yield $index => $this->map($item);',
            $this->printer->prettyPrint([StatementBuilder::yieldMapOverIterable('items', 'item', 'index', 'map', ['item'])]),
        );
    }

    #[Test]
    public function matchBuilderCreatesDefaultArm(): void
    {
        $match = MatchBuilder::onVariable('className', [
            [
                'conditions' => [new Scalar\String_('\\Vendor\\Foo')],
                'body' => new Expr\Variable('foo'),
            ],
        ], new Expr\Throw_(ExpressionBuilder::newRuntimeException('missing')));

        self::assertCount(2, $match->arms);
        self::assertNull($match->arms[1]->conds);
        self::assertStringContainsString(
            'default => throw new \\RuntimeException(\'missing\')',
            $this->printer->prettyPrintExpr($match),
        );
    }

    #[Test]
    public function matchBuilderCreatesStringClassNameArms(): void
    {
        $match = MatchBuilder::onClassNameStrings('className', [
            [
                'classNames' => ['\\Vendor\\Foo', '\\Vendor\\Bar'],
                'body' => ExpressionBuilder::thisMethod('handle'),
            ],
        ]);

        self::assertCount(1, $match->arms);
        self::assertNotNull($match->arms[0]->conds);
        self::assertCount(2, $match->arms[0]->conds);
        self::assertStringContainsString(
            '\'\\Vendor\\Foo\', \'\\Vendor\\Bar\' => $this->handle()',
            $this->printer->prettyPrintExpr($match),
        );
    }
}
