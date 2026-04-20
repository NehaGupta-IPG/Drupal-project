<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Bootstrap;

use Drupal\Core\DependencyInjection\PhpContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base container which throws an exception.
 */
class ExceptionContainer extends PhpContainer {

  /**
   * {@inheritdoc}
   */
  public function get(string $id, int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE): ?object {
    if ($id === 'http_kernel') {
      throw new \Exception('Thrown exception during Container::get');
    }
    else {
      return parent::get($id, $invalidBehavior);
    }
  }

}
