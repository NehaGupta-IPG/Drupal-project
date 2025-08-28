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

final class DrupalAnnotationToAttributeRector extends AbstractRector implements MinPhpVersionInterface
{
    private array $annotationTargets = [
        '@after' => [],
        '@afterClass' => [],
        '@author' => [],
        '@backupGlobals' => [],
        '@backupStaticAttributes' => [],
        '@before' => [],
        '@beforeClass' => [],
        '@covers' => [
            'converter' => 'convertCovers',
        ],
        '@coversDefaultClass' => [
            'converter' => 'convertCoversDefaultClass',
        ],
        '@coversNothing' => [
            'converter' => 'convertCoversNothing',
        ],
        '@dataProvider' => [
            'converter' => 'convertDataProvider',
        ],
        '@depends' => [
            'converter' => 'convertDepends',
        ],
        '@doesNotPerformAssertions' => [],
        '@group' => [
            'converter' => 'convertGroup',
        ],
        '@large' => [],
        '@medium' => [
            'converter' => 'convertMedium',
        ],
        '@preserveGlobalState' => [
            'converter' => 'convertPreserveGlobalState',
        ],
        '@requires' => [
            'converter' => 'convertRequires',
        ],
        '@runTestsInSeparateProcesses' => [
            'converter' => 'convertRunTestsInSeparateProcesses',
        ],
        '@runInSeparateProcess' => [
            'converter' => 'convertRunInSeparateProcess',
        ],
        '@small' => [],
        '@test' => [],
        '@testdox' => [],
        '@testWith' => [
            'multiline' => true,
            'converter' => 'convertTestWith',
        ],
        '@ticket' => [],
        '@uses' => [
            'converter' => 'convertUses',
        ],
    ];

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
        return new RuleDefinition('Change annotations to attributes in Drupal test codebase', []);
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

        foreach ($this->annotationTargets as $target => $targetConfig) {

            /** @var PhpDocTagNode[] $desiredTagValueNodes */
            $desiredTagValueNodes = $phpDocInfo->getTagsByName($target);

            foreach ($desiredTagValueNodes as $desiredTagValueNode) {
                if (! $desiredTagValueNode->value instanceof GenericTagValueNode) {
                    continue;
                }

                $attributeValue = $this->resolveAttributeValue($desiredTagValueNode->value, []);
                $attributeValueLines = count(explode("\n", $attributeValue));

                $targetConverter = $targetConfig['converter'] ?? false;

                if (! $targetConverter) {
                    throw new \RuntimeException('No converter for ' . $target . ' annotation');
                }

                if ((! $targetConfig['multiline'] ?? false) && ($attributeValueLines > 1)) {
                    throw new \RuntimeException('Unexepected multiline annotation value in ' . var_export([self::$currentClassName, $nodeName, $target, $attributeValue], true));
                }

                call_user_func([$this, $targetConverter], $node, $phpDocInfo, $desiredTagValueNode);
                $hasChanged = true;
            }
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
                        $newLines[] = "  /**";
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

    private function convertRunTestsInSeparateProcesses(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {

        $attributeGroup = $this->phpAttributeGroupFactory->createFromClass(
            RunTestsInSeparateProcesses::class,
        );

        $node->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertRunInSeparateProcess(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {

        $attributeGroup = $this->phpAttributeGroupFactory->createFromClass(
            RunInSeparateProcess::class,
        );

        $node->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertPreserveGlobalState(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {
        $preserve = match ($desiredTagValueNode->value->value) {
            'enabled' => true,
            'disabled' => false,
        };

        $attributeGroup = $this->phpAttributeGroupFactory->createFromClassWithItems(
            PreserveGlobalState::class,
            [$preserve],
        );

        $node->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertDataProvider(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {

        $attributeGroup = $this->phpAttributeGroupFactory->createFromClassWithItems(
            DataProvider::class,
            [$desiredTagValueNode->value->value],
        );

        $node->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertDepends(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {

        $attributeGroup = $this->phpAttributeGroupFactory->createFromClassWithItems(
            Depends::class,
            [$desiredTagValueNode->value->value],
        );

        $node->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertGroup(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {
        $value = $desiredTagValueNode->value->value;
        $stringValue = new String_($value);

        $attributeGroup = match ($value) {
            'legacy' => $this->phpAttributeGroupFactory->createFromClass(
                IgnoreDeprecations::class,
            ),
            default => $this->phpAttributeGroupFactory->createFromClassWithItems(
                Group::class,
                [$stringValue],
            ),
        };

        $node->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertTestWith(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {
        $values = explode("\n", $desiredTagValueNode->value->value);

        foreach ($values as $value) {
            $data = $this->parseTestWithData($value);
            $attributeGroup = $this->phpAttributeGroupFactory->createFromClassWithItems(
                TestWith::class,
                [$data],
            );
            $node->attrGroups[] = $attributeGroup;
        };

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertRequires(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {
        $value = explode(' ', $desiredTagValueNode->value->value);

        $attributeGroup = match ($value[0]) {
            'extension' => $this->phpAttributeGroupFactory->createFromClassWithItems(
                RequiresPhpExtension::class,
                [$value[1]],
            ),
            default => throw new \RuntimeException('Unsupported require "' . $value[0] . '"'),
        };

        $node->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertCoversDefaultClass(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {

        $classLikeName = $desiredTagValueNode->value->value;
        $classLikeName = \ltrim($classLikeName, '\\');
        $fullyQualified = new FullyQualified($classLikeName);
        self::$testedClassName = $fullyQualified->name;
        $classConst = new ClassConstFetch($fullyQualified, 'class');

        $attributeGroup = $this->phpAttributeGroupFactory->createFromClassWithItems(
            CoversClass::class,
            [$classConst],
        );

        $node->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertCovers(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {

        $legacyCoversValueNode = new PhpDocTagNode('@legacy-covers', $desiredTagValueNode->value);
        $phpDocInfo->addPhpDocTagNode($legacyCoversValueNode);

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);
    }

    private function convertCoversNothing(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {

        $attributeGroup = $this->phpAttributeGroupFactory->createFromClass(
            CoversNothing::class,
        );

        $node->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertUses(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {

        $classLikeName = $desiredTagValueNode->value->value;
        $classLikeName = \ltrim($classLikeName, '\\');
        $fullyQualified = new FullyQualified($classLikeName);
        $classConst = new ClassConstFetch($fullyQualified, 'class');

        $attributeGroup = $this->phpAttributeGroupFactory->createFromClassWithItems(
            UsesClass::class,
            [$classConst],
        );


        // prevent duplicates
        $existsAlready = false;

        // Attach the attribute to the class.
        if (! $existsAlready) {
            self::$currentClassNode->attrGroups[] = $attributeGroup;
        }

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertMedium(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {

        $attributeGroup = $this->phpAttributeGroupFactory->createFromClass(
            Medium::class,
        );

        // prevent duplicates
        $existsAlready = false;
        foreach (self::$currentClassNode->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->toString() === Medium::class) {
                    $existsAlready = true;
                    break 2;
                }
            }
        }

        // Attach the attribute to the class.
        if (! $existsAlready) {
            self::$currentClassNode->attrGroups[] = $attributeGroup;
        }

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

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
#        __DIR__ . '/core/modules/**/tests/src/Build',
#        __DIR__ . '/core/modules/**/tests/src/Functional',
        __DIR__ . '/core/modules/**/tests/src/Kernel',
#        __DIR__ . '/core/modules/**/tests/src/Unit',
#        __DIR__ . '/core/tests/Drupal/Tests/Component',
#        __DIR__ . '/core/tests/Drupal/Tests',
#        __DIR__ . '/core/tests/Drupal/KernelTests',
#        __DIR__ . '/core/tests/Drupal/FunctionalTests',
#        __DIR__ . '/core/tests/Drupal/FunctionalJavascriptTests',
#        __DIR__ . '/core',
#        __DIR__ . '/composer',
    ])
    ->withSkip([
        __DIR__ . '/core/tests/Drupal/Tests/Component/Annotation/Doctrine/Fixtures',

        # Modules' Unit tests skipped.
        __DIR__ . '/core/modules/package_manager/tests/src/Unit/InstalledPackagesListTest.php',
        __DIR__ . '/core/modules/mysql/tests/src/Unit/InstallTasksTest.php',
        __DIR__ . '/core/modules/views/tests/src/Unit/Plugin/Block/ViewsBlockTest.php',
        __DIR__ . '/core/modules/comment/tests/src/Unit/CommentLinkBuilderTest.php',
        __DIR__ . '/core/modules/language/tests/src/Unit/LanguageNegotiationUrlTest.php',
        __DIR__ . '/core/modules/migrate_drupal/tests/src/Unit/source/d6/Drupal6SqlBaseTest.php',
        __DIR__ . '/core/modules/views/tests/src/Unit/Plugin/field/FieldPluginBaseTest.php',
        __DIR__ . '/core/modules/views/tests/src/Unit/Routing/ViewPageControllerTest.php',
        __DIR__ . '/core/modules/migrate/tests/src/Unit/Exception/MigrateSkipProcessExceptionTest.php',

        # Core's Unit tests skipped.
        __DIR__ . '/core/tests/Drupal/Tests/Component',
        __DIR__ . '/core/tests/Drupal/Tests/Core/Config/TypeResolverTest.php',
        __DIR__ . '/core/tests/Drupal/Tests/Core/Lock/LockBackendAbstractTest.php',
        __DIR__ . '/core/tests/Drupal/Tests/Core/Asset/LibraryDiscoveryTest.php',
        __DIR__ . '/core/tests/Drupal/Tests/Core/Entity/Sql/SqlContentEntityStorageSchemaTest.php',
        __DIR__ . '/core/tests/Drupal/Tests/Core/Extension/ModuleHandlerTest.php',
        __DIR__ . '/core/tests/Drupal/Tests/Core/Form/ConfigTargetTest.php',
        __DIR__ . '/core/tests/Drupal/Tests/Core/StringTranslation/StringTranslationTraitTest.php',
        __DIR__ . '/core/tests/Drupal/Tests/Core/TypedData/RecursiveContextualValidatorTest.php',
        __DIR__ . '/core/tests/Drupal/Tests/Core/Database/ConnectionTest.php',
        __DIR__ . '/core/tests/Drupal/Tests/Core/Test/TestDatabaseTest.php',
        __DIR__ . '/core/tests/Drupal/Tests/Core/DrupalTest.php',
        __DIR__ . '/core/tests/Drupal/Tests/Core/Extension/Requirement/RequirementSeverityTest.php',
        __DIR__ . '/core/tests/Drupal/Tests/Core/Utility/CallableResolverTest.php',

        # Modules' Kernel tests skipped.
        __DIR__ . '/core/modules/editor/tests/src/Kernel/EditorValidationTest.php',
        __DIR__ . '/core/modules/node/tests/src/Kernel/NodeTypeValidationTest.php',
        __DIR__ . '/core/modules/ckeditor5/tests/src/Kernel/ConfigAction/AddItemToToolbarConfigActionTest.php',
        __DIR__ . '/core/modules/system/tests/src/Kernel/Entity/MenuValidationTest.php',
        __DIR__ . '/core/modules/package_manager/tests/src/Kernel/ComposerInspectorTest.php',
        __DIR__ . '/core/modules/file/tests/src/Kernel/FileItemValidationTest.php',

        __DIR__ . '/core/tests/Drupal/FunctionalTests/BrowserTestBaseTest.php',
        __DIR__ . '/core/modules/ckeditor5/tests/src/FunctionalJavascript/SourceEditingEmptyElementTest.php',
        '*/ProxyClass/*',
        '*.api.php',
    ])
    ->withRules([
        DrupalAnnotationToAttributeRector::class,
    ])
#    ->withoutParallel()
    ->withImportNames(
        importDocBlockNames: false,
        importShortClasses: false,
        removeUnusedImports: false,
    );
