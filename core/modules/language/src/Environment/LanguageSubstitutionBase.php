<?php

declare(strict_types=1);

namespace Drupal\language\Environment;

use Drupal\Core\Environment\EnvironmentParameter;
use Drupal\Core\Environment\SubstitutionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Abstract base class for language substitution.
 */
abstract class LanguageSubstitutionBase implements SubstitutionInterface {

  public function __construct(
    protected readonly LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Returns the original language.
   */
  abstract protected function getOriginalLanguage(): LanguageInterface;

  /**
   * Sets the override language.
   */
  abstract protected function overrideLanguage(LanguageInterface $overrideLanguage): void;

  /**
   * Restores the original language.
   */
  abstract protected function restoreLanguage(LanguageInterface $originalLanguage): void;

  /**
   * {@inheritdoc}
   */
  public function executeInEnvironment(EnvironmentParameter $param, callable $callback): mixed {
    $originalLanguage = $this->getOriginalLanguage();
    $overrideLanguage = NULL;

    $overrideLangcode = (string) $param->value;

    if ($overrideLangcode !== $originalLanguage->getId()) {
      $overrideLanguage = $this->languageManager->getLanguage($overrideLangcode);
      if ($overrideLanguage) {
        $this->overrideLanguage($overrideLanguage);
      }
      elseif (!$param->optional) {
        throw new \InvalidArgumentException(sprintf('Failed to load the specified override language %s', $overrideLangcode));
      }
    }
    try {
      return $callback();
    }
    finally {
      $this->restoreLanguage($originalLanguage);
    }
  }

}
