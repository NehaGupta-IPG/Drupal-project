<?php

declare(strict_types=1);

namespace Drupal\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Ensures that test setUp() methods are protected.
 *
 * @implements Rule<InClassMethodNode>
 *
 * @internal
 */
final class SetUpMethodVisibility implements Rule {

  public function __construct(
    private ReflectionProvider $reflectionProvider,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeType(): string {
    return InClassMethodNode::class;
  }

  /**
   * {@inheritdoc}
   */
  public function processNode(Node $node, Scope $scope): array {
    $method = $node->getMethodReflection();
    if (strtolower($method->getName()) !== 'setup' || !$method->isPublic()) {
      return [];
    }

    $class = $node->getClassReflection();
    if (!$class->isSubclassOfClass($this->reflectionProvider->getClass(TestCase::class))) {
      return [];
    }

    return [
      RuleErrorBuilder::message('Test setUp() methods must be protected.')
        ->line($node->getStartLine())
        ->build(),
    ];
  }

}
