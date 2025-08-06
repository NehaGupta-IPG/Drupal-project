<?php

declare(strict_types=1);

namespace Drupal\Core\Environment;

use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageDefault;
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
  protected function getOriginalLanguage(): Language {
    return $this->languageDefault->get();
  }

  /**
   * {@inheritdoc}
   */
  protected function overrideLanguage(Language $overrideLanguage): void {
    $this->languageDefault->set($overrideLanguage);
  }

  /**
   * {@inheritdoc}
   */
  protected function restoreLanguage(Language $originalLanguage): void {
    $currentLanguage = $this->languageDefault->get();
    if ($currentLanguage->getId() !== $originalLanguage->getId()) {
      $this->languageDefault->set($originalLanguage);
    }
  }

}
