<?php

declare(strict_types=1);

namespace Drupal\PHPStan\Rules;

// cspell:ignore analyse testdox

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\BrowserTestBase;
use Drupal\TestTools\Attribute\ShareEnvironment;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Rules class-level PHPUnit test metadata in test classes.
 *
 * @implements Rule<\PHPStan\Node\InClassNode>
 *
 * @internal
 */
final class TestClassClassMetadata implements Rule {

  /**
   * PHPUnit metadata annotations.
   *
   * @var list<string>
   */
  private array $annotationTargets = [
    '@after',
    '@afterClass',
    '@author',
    '@backupGlobals',
    '@backupStaticAttributes',
    '@before',
    '@beforeClass',
    '@covers',
    '@coversClass',
    '@coversDefaultClass',
    '@coversNothing',
    '@dataProvider',
    '@depends',
    '@doesNotPerformAssertions',
    '@group',
    '@large',
    '@legacy-covers',
    '@medium',
    '@postCondition',
    '@preCondition',
    '@preserveGlobalState',
    '@requires',
    '@runInSeparateProcess',
    '@runTestsInSeparateProcesses',
    '@small',
    '@test',
    '@testdox',
    '@testWith',
    '@ticket',
    '@uses',
  ];

  public function __construct(
    private ReflectionProvider $reflectionProvider,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeType(): string {
    return InClassNode::class;
  }

  /**
   * {@inheritdoc}
   */
  public function processNode(Node $node, Scope $scope): array {
    $class = $node->getClassReflection();

    // We only process PHPUnit test classes here.
    if (!$class->isSubclassOfClass($this->reflectionProvider->getClass(TestCase::class))) {
      return [];
    }

    $fails = [];

    if ($class->isAbstract()) {
      // Abstract test classes (i.e. base test classes) should not have any
      // metadata on the class definition, neither attributes nor annotations.
      foreach ($class->getAttributes() as $attribute) {
        if (str_starts_with($attribute->getName(), 'PHPUnit\\Framework\\Attributes\\')) {
          $fails[] = RuleErrorBuilder::message("Abstract test class {$class->getName()} must not add attribute {$attribute->getName()}.")
            ->identifier('abstractTestClass.metadataForbidden')
            ->line($node->getStartLine())
            ->build();
        }
      }
      // The attribute is not inherited, so on a base class it does nothing.
      if ($this->declaresShareEnvironment($class)) {
        $fails[] = RuleErrorBuilder::message("Abstract test class {$class->getName()} must not add attribute " . ShareEnvironment::class . ". The attribute is not inherited, so each concrete test class declares it.")
          ->identifier('abstractTestClass.metadataForbidden')
          ->line($node->getStartLine())
          ->build();
      }

      $resolvedPhpDoc = $class->getResolvedPhpDoc();
      if ($resolvedPhpDoc) {
        foreach ($resolvedPhpDoc->getPhpDocNodes() as $phpDocNode) {
          foreach ($phpDocNode->getTags() as $tag) {
            if (in_array($tag->name, $this->annotationTargets, TRUE)) {
              $fails[] = RuleErrorBuilder::message("Abstract test class {$class->getName()} must not add annotation {$tag->name}.")
                ->identifier('abstractTestClass.metadataForbidden')
                ->line($node->getStartLine())
                ->build();
            }
          }
        }
      }
    }
    else {
      // Concrete test classes should no longer have PHPUnit metadata
      // annotations.
      $resolvedPhpDoc = $class->getResolvedPhpDoc();
      if ($resolvedPhpDoc) {
        foreach ($resolvedPhpDoc->getPhpDocNodes() as $phpDocNode) {
          foreach ($phpDocNode->getTags() as $tag) {
            if (in_array($tag->name, $this->annotationTargets, TRUE)) {
              $fails[] = RuleErrorBuilder::message("Test class {$class->getName()} must not add annotation {$tag->name}.")
                ->identifier('testClass.metadataForbidden')
                ->line($node->getStartLine())
                ->build();
            }
          }
        }
      }

      // Concrete test classes should have the Group attribute and in some cases
      // the RunTestsInSeparateProcesses attribute.
      $has_group_attribute = FALSE;
      $has_run_in_separate_process_attribute = FALSE;
      foreach ($class->getAttributes() as $attribute) {
        switch ($attribute->getName()) {
          case Group::class:
            $has_group_attribute = TRUE;
            break;

          case RunTestsInSeparateProcesses::class:
            $has_run_in_separate_process_attribute = TRUE;
            break;
        }
      }
      $is_kernel_test = $class->isSubclassOfClass($this->reflectionProvider->getClass(KernelTestBase::class));
      $should_run_in_separate_process =
        $is_kernel_test ||
        $class->isSubclassOfClass($this->reflectionProvider->getClass(BrowserTestBase::class));
      if ($should_run_in_separate_process && !$has_run_in_separate_process_attribute) {
        $fails[] = RuleErrorBuilder::message("Test class {$class->getName()} must have attribute \PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses.")
          ->identifier('testClass.missingAttribute.RunTestsInSeparateProcesses')
          ->line($node->getStartLine())
          ->build();
      }
      if ($is_kernel_test && !$this->declaresShareEnvironment($class)) {
        $fails[] = RuleErrorBuilder::message("Kernel test class {$class->getName()} must have attribute \\" . ShareEnvironment::class . ". Add #[ShareEnvironment], or #[ShareEnvironment(FALSE)] if the class cannot share its environment.")
          ->identifier('testClass.missingAttribute.ShareEnvironment')
          ->line($node->getStartLine())
          ->build();
      }
      if (!$has_group_attribute) {
        $fails[] = RuleErrorBuilder::message("Test class {$class->getName()} must have attribute \PHPUnit\Framework\Attributes\Group.")
          ->identifier('testClass.missingAttribute.Group')
          ->line($node->getStartLine())
          ->build();
      }

    }

    return $fails;
  }

  /**
   * Checks if a class declares the ShareEnvironment attribute itself.
   *
   * @param \PHPStan\Reflection\ClassReflection $class
   *   The class to check.
   *
   * @return bool
   *   TRUE if the class declares the attribute, with or without arguments.
   *
   * @see \Drupal\TestTools\Attribute\ShareEnvironment::isEnabledFor()
   */
  private function declaresShareEnvironment(ClassReflection $class): bool {
    // This does not use "ClassReflection::getAttributes()", which is what the
    // other attribute checks loop over. PHPStan keeps an attribute that has
    // arguments only when it can reflect the attribute class, and it does not
    // autoload the test namespaces, so "#[ShareEnvironment(FALSE)]" is dropped
    // from that list and only "#[ShareEnvironment]" is in it.
    return $class->getNativeReflection()->getAttributes(ShareEnvironment::class) !== [];
  }

}
