<?php

declare(strict_types=1);

namespace Drupal\Core\Environment;

/**
 * Language substitution fallback.
 */
class LanguageSubstitution implements SubstitutionInterface {

  use SubstitutionApplyTrait;

  /**
   * {@inheritdoc}
   */
  public function executeInEnvironment(EnvironmentParameter $param, callable $callback): mixed {
    return $callback();
  }

}
