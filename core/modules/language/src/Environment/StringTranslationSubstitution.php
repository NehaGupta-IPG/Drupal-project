<?php

declare(strict_types=1);

namespace Drupal\language\Environment;

use Drupal\Core\Environment\SubstitutionApplyTrait;
use Drupal\Core\Environment\SubstitutionInterface;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslationManager;

/**
 * Switches the string translation language.
 */
class StringTranslationSubstitution extends LanguageSubstitutionBase implements SubstitutionInterface {

  use SubstitutionApplyTrait;

  public function __construct(
    LanguageManagerInterface $languageManager,
    protected readonly LanguageDefault $languageDefault,
    protected readonly TranslationManager $translationManager,
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
    $this->translationManager->setDefaultLangcode($overrideLanguage->getId());
    $this->translationManager->reset();
  }

  /**
   * {@inheritdoc}
   */
  protected function restoreLanguage(LanguageInterface $originalLanguage): void {
    $this->translationManager->setDefaultLangcode($originalLanguage->getId());
    $this->translationManager->reset();
  }

}
