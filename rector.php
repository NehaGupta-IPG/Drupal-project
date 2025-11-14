<?php

declare(strict_types=1);

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

use Rector\Config\RectorConfig;

final class MyCustomRector extends AbstractRector implements MinPhpVersionInterface
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
        return [Class_::class, ClassMethod::class];
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

            if (! $classReflection->isSubclassOf(TestCase::class)) {
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

        $phpDocInfo = $this->phpDocInfoFactory->createFromNode($node);
        if (! $phpDocInfo instanceof PhpDocInfo) {
            return null;
        }

        $hasChanged = false;

        $legacyCovers = $phpDocInfo->getTagsByName('@legacy-covers');

        if (empty($legacyCovers) || count($legacyCovers) > 1) {
            return null;
        }

        $target = $legacyCovers[0];
        if (str_starts_with($target->value->value, '::')) {
          $targetMethod = substr($target->value->value, 2);
        }
        else {
          return null;
        }

        if (str_starts_with($nodeName, 'test' . ucfirst($targetMethod))) {
          $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $target);
          $hasChanged = true;
        }

        if ($hasChanged) {
            $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo(self::$currentClassNode);
            $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($node);

            // Ensure a class-level PhpDoc always exists.
            if (self::$currentClassNode instanceof Class_) {
                $hasDoc = (bool) self::$currentClassNode->getDocComment();
                if ($hasDoc) {
                    $check = preg_match("/.*\/\*\*.*\n.*\*( @|\n)/i",  self::$currentClassNode->getDocComment()->getText());
                }
                if (!$hasDoc || $check) {
                    if (self::$testedClassName) {
                        $newDesc = "Tests " . self::$testedClassName . ".";
                    }
                    else {
                        $prettifiedClassName = preg_replace("/(.*)( \\(.*\\))/i", '${1}', self::$namePrettifier->prettifyTestClassName(self::$currentClassName));
                        $newDesc = "Tests " . $prettifiedClassName . ".";
                    }
                    if ($hasDoc) {
                        $lines = explode("\n", self::$currentClassNode->getDocComment()->getText());
                        $newLines = [];
                        $newLines[] = array_shift($lines);
                        $newLines[] = " * {$newDesc}";
                        $newLines[] = " *";
                        $newLines = array_merge($newLines, $lines);
                    } else {
                        $newLines = [];
                        $newLines[] = "/**";
                        $newLines[] = " * {$newDesc}";
                        $newLines[] = " */";
                    }
                    self::$currentClassNode->setDocComment(new Doc(implode("\n", $newLines)));
                }
            }

            // Ensure a method-level PhpDoc always exist and has a description.
            if ($node instanceof ClassMethod) {
                $hasDoc = (bool) $node->getDocComment();
                if ($hasDoc) {
                    $check = preg_match("/.*\/\*\*.*\n.*\*( @|\n)/i", $node->getDocComment()->getText());
                }
                if (!$hasDoc || $check) {
                    $prettifiedTestMethodName = lcfirst(self::$namePrettifier->prettifyTestMethodName($this->getName($node)));
                    if ($hasDoc) {
                        $lines = explode("\n", $node->getDocComment()->getText());
                        $newLines = [];
                        $newLines[] = array_shift($lines);
                        $newLines[] = "   * Tests {$prettifiedTestMethodName}.";
                        $newLines[] = "   *";
                        $newLines = array_merge($newLines, $lines);
                    } else {
                        $newLines = [];
                        $newLines[] = "/**";
                        $newLines[] = "   * Tests {$prettifiedTestMethodName}.";
                        $newLines[] = "   */";
                    }
                    $node->setDocComment(new Doc(implode("\n", $newLines)));
                }
            }

            return $node;
        }

        return null;
    }

}

return RectorConfig::configure()
    ->withBootstrapFiles([
        __DIR__ . '/vendor/palantirnet/drupal-rector/config/drupal-phpunit-bootstrap-file.php',
    ])
    ->withPaths([
        __DIR__ . '/core/tests/Drupal/Tests',
    ])
    ->withSkip([
        '*/vendor/*',
        '*/ProxyClass/*',
        '*.api.php',
    ])
    ->withRules([
        MyCustomRector::class,
    ])
    ->withImportNames(
        importDocBlockNames: false,
        importShortClasses: false,
        removeUnusedImports: false,
    );
