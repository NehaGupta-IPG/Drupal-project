<?php

declare(strict_types=1);

namespace Drupal\Core\Environment;

use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Overrides the config language.
 */
class ConfigOverrideLanguageSubstitution implements SubstitutionInterface {

  use SubstitutionApplyTrait;

  /**
   * Constructs a new config override language substitution.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   */
  public function __construct(
    protected readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function executeInEnvironment(EnvironmentParameter $param, callable $callback): mixed {
    $originalLanguage = $this->languageManager->getConfigOverrideLanguage();
    $overrideLanguage = NULL;

    $overrideLangcode = (string) $param->value;

    if ($overrideLangcode !== $originalLanguage->getId()) {
      $overrideLanguage = $this->languageManager->getLanguage($overrideLangcode);
      if ($overrideLanguage) {
        $this->languageManager->setConfigOverrideLanguage($overrideLanguage);
      }
      else {
        throw new \InvalidArgumentException(sprintf('Failed to load the specified override language %s', $overrideLangcode));
      }
    }
    try {
      return $callback();
    }
    finally {
      $currentLanguage = $this->languageManager->getConfigOverrideLanguage();
      if ($currentLanguage->getId() !== $originalLanguage->getId()) {
        $this->languageManager->setConfigOverrideLanguage($originalLanguage);
      }
    }
  }

}
