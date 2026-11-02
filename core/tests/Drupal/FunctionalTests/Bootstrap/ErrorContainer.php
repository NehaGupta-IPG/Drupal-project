<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Bootstrap;

use Drupal\Core\DependencyInjection\PhpContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Container base class which triggers an error.
 */
class ErrorContainer extends PhpContainer {

  /**
   * {@inheritdoc}
   */
  public function get(string $id, int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE): ?object {
    if ($id === 'http_kernel') {
      // Enforce a recoverable error.
      $callable = function (ErrorContainer $container): void {
      };
      return $callable(1);
    }
    return parent::get($id, $invalidBehavior);
  }

}
