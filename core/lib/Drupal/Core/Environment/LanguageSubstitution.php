<?php

declare(strict_types=1);

namespace Drupal\Core\Environment;

/**
 * Language substitution fallback.
 *
 * This services is decorated by MultilingualSubstitutionDecorator if the
 * language module is enabled.
 *
 * @see \Drupal\language\Environment\MultilingualSubstitution
 * @see \Drupal\language\Environment\MultilingualSubstitutionDecorator
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
