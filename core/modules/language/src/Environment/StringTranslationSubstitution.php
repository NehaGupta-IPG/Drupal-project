<?php

declare(strict_types=1);

namespace Drupal\Core\Environment;

use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageDefault;
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
  protected function getOriginalLanguage(): Language {
    return $this->languageDefault->get();
  }

  /**
   * {@inheritdoc}
   */
  protected function overrideLanguage(Language $overrideLanguage): void {
    $this->translationManager->setDefaultLangcode($overrideLanguage->getId());
    $this->translationManager->reset();
  }

  /**
   * {@inheritdoc}
   */
  protected function restoreLanguage(Language $originalLanguage): void {
    $this->translationManager->setDefaultLangcode($originalLanguage->getId());
    $this->translationManager->reset();
  }

}
