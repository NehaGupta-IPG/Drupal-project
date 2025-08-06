<?php

declare(strict_types=1);

namespace Drupal\language\Environment;

use Drupal\Core\Environment\SubstitutionApplyTrait;
use Drupal\Core\Environment\SubstitutionInterface;
use Drupal\Core\Language\Language;

/**
 * Switches the config override language.
 */
class ConfigOverrideLanguageSubstitution extends LanguageSubstitutionBase implements SubstitutionInterface {

  use SubstitutionApplyTrait;

  /**
   * {@inheritdoc}
   */
  protected function getOriginalLanguage(): Language {
    return $this->languageManager->getConfigOverrideLanguage();
  }

  /**
   * {@inheritdoc}
   */
  protected function overrideLanguage(Language $overrideLanguage): void {
    $this->languageManager->setConfigOverrideLanguage($overrideLanguage);
  }

  /**
   * {@inheritdoc}
   */
  protected function restoreLanguage(Language $originalLanguage): void {
    $currentLanguage = $this->languageManager->getConfigOverrideLanguage();
    if ($currentLanguage->getId() !== $originalLanguage->getId()) {
      $this->languageManager->setConfigOverrideLanguage($originalLanguage);
    }
  }

}
