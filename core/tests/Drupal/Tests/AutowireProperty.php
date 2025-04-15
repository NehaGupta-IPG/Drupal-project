<?php

declare(strict_types=1);

namespace Drupal\Tests;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Autowire properties.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class AutowireProperty extends Autowire {

  public function __construct(
    ?string $service = NULL,
    ?string $expression = NULL,
    ?string $param = NULL,
  ) {
    if ($service !== NULL || $expression !== NULL || $param !== NULL) {
      parent::__construct(
        service: $service,
        expression: $expression,
        param: $param,
      );
    }
  }

}
