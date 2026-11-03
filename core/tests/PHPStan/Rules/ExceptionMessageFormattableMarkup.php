<?php

declare(strict_types=1);

namespace Drupal\PHPStan\Rules;

use Drupal\Component\Render\FormattableMarkup;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Ensures exception messages do not use FormattableMarkup.
 *
 * @implements Rule<Expr>
 *
 * @internal
 */
final class ExceptionMessageFormattableMarkup implements Rule {

  public function __construct(
    private ReflectionProvider $reflectionProvider,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeType(): string {
    return Expr::class;
  }

  /**
   * {@inheritdoc}
   */
  public function processNode(Node $node, Scope $scope): array {
    if ($node instanceof New_
      && $node->class instanceof Name
      && $this->reflectionProvider->hasClass($scope->resolveName($node->class))
      && $this->reflectionProvider->getClass($scope->resolveName($node->class))->isSubclassOfClass($this->reflectionProvider->getClass(\Throwable::class))
      && isset($node->args[0])
      && in_array(FormattableMarkup::class, $scope->getType($node->args[0]->value)->getObjectClassNames(), TRUE)) {
      $message = $node->args[0];
      return [
        RuleErrorBuilder::message('Exception messages must not use FormattableMarkup.')
          ->line($message->getStartLine())
          ->identifier('drupal.exceptionMessage.formattableMarkup')
          ->build(),
      ];
    }

    return [];
  }

}
