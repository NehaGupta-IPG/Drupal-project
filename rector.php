<?php

declare(strict_types=1);

use Drupal\Tests\BrowserTestBase;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\ParserFactory;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\Reflection\ClassReflection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Logging\TestDox\NamePrettifier;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Rector\Config\RectorConfig;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\PhpAttribute\NodeFactory\PhpAttributeGroupFactory;
use Rector\PHPUnit\ValueObject\AnnotationWithValueToAttribute;
use Rector\Rector\AbstractRector;
use Rector\Reflection\ReflectionResolver;
use Rector\ValueObject\PhpVersionFeature;
use Rector\VersionBonding\Contract\MinPhpVersionInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

final class DrupalCustomRector extends AbstractRector implements MinPhpVersionInterface
{
    private static ?string $testedClassName;
    private static ?string $currentClassName;
    private static ?Node $currentClassNode;
    private static NamePrettifier $namePrettifier;

    public function __construct(
        private readonly PhpDocTagRemover $phpDocTagRemover,
        private readonly PhpAttributeGroupFactory $phpAttributeGroupFactory,
        private readonly DocBlockUpdater $docBlockUpdater,
        private readonly PhpDocInfoFactory $phpDocInfoFactory,
        private readonly ReflectionResolver $reflectionResolver,
    ) {
        self::$namePrettifier = new NamePrettifier();
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Drupal custom rector', []);
    }

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function provideMinPhpVersion(): int
    {
        return PhpVersionFeature::ATTRIBUTES;
    }

    /**
     * @param Class_|ClassMethod $node
     */
    public function refactor(Node $node): ?Node
    {
        $nodeName = $this->getName($node);

        if ($node instanceof Class_) {
            $classReflection = $this->reflectionResolver->resolveClassReflection($node);
            if (! $classReflection instanceof ClassReflection) {
                return null;
            }

            if (! $classReflection->isClass()) {
                return null;
            }

            if (! $classReflection->isSubclassOf(BrowserTestBase::class)) {
                self::$testedClassName = null;
                self::$currentClassName = null;
                self::$currentClassNode = null;
                return null;
            }

            self::$testedClassName = null;
            self::$currentClassName = $nodeName;
            self::$currentClassNode = $node;
        }

        if (! isset(self::$currentClassName)) {
            return null;
        }

        $hasChanged = false;

        $attributeGroup = $this->phpAttributeGroupFactory->createFromClass(
            RunTestsInSeparateProcesses::class,
        );
        $node->attrGroups[] = $attributeGroup;

        $hasChanged = true;

        if ($hasChanged) {
            return $node;
        }

        return null;
    }

    private function resolveAttributeValue(
        GenericTagValueNode $genericTagValueNode,
        array $valueMap = [],
    ): mixed {
        if ($valueMap === []) {
            // no map? convert value as it is
            return $genericTagValueNode->value;
        }

        $originalValue = strtolower($genericTagValueNode->value);
        return $valueMap[$originalValue];
    }

    private function parseTestWithData(
        string $data,
    ): Array_ {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse("<?php\n\$array = {$data};");
        return $ast[0]->expr->expr;
    }

}

return RectorConfig::configure()
    ->withBootstrapFiles([
        __DIR__ . '/vendor/palantirnet/drupal-rector/config/drupal-phpunit-bootstrap-file.php',
    ])
    ->withPaths([
        __DIR__,
    ])
    ->withSkip([
        '*/vendor/*',
        '*/ProxyClass/*',
        '*.api.php',
    ])
    ->withRules([
        DrupalCustomRector::class,
    ])
    ->withImportNames(
        importDocBlockNames: false,
        importShortClasses: false,
        removeUnusedImports: false,
    );
