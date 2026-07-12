<?php

declare(strict_types=1);

namespace Drupal\language\Environment;

use Drupal\Core\Environment\SubstitutionApplyTrait;
use Drupal\Core\Environment\SubstitutionInterface;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Switches the language default.
 */
class DefaultLanguageSubstitution extends LanguageSubstitutionBase implements SubstitutionInterface {

  use SubstitutionApplyTrait;

  public function __construct(
    LanguageManagerInterface $languageManager,
    protected readonly LanguageDefault $languageDefault,
  ) {
    parent::__construct($languageManager);
  }

  /**
   * {@inheritdoc}
   */
  protected function getOriginalLanguage(): LanguageInterface {
    return $this->languageDefault->get();
  }

  /**
   * {@inheritdoc}
   */
  protected function overrideLanguage(LanguageInterface $overrideLanguage): void {
    $this->languageDefault->set($overrideLanguage);
  }

  /**
   * {@inheritdoc}
   */
  protected function restoreLanguage(LanguageInterface $originalLanguage): void {
    $currentLanguage = $this->languageDefault->get();
    if ($currentLanguage->getId() !== $originalLanguage->getId()) {
      $this->languageDefault->set($originalLanguage);
    }
  }

}
