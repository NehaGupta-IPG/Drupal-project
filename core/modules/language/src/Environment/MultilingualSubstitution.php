<?php

declare(strict_types=1);

namespace Drupal\language\Environment;

use Drupal\Core\Environment\EnvironmentParameter;
use Drupal\Core\Environment\SubstitutionApplyTrait;
use Drupal\Core\Environment\SubstitutionInterface;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslationManager;

/**
 * Switches the language.
 */
class MultilingualSubstitution implements SubstitutionInterface {

  use SubstitutionApplyTrait;

  /**
   * Config override substitution.
   */
  protected readonly ConfigOverrideLanguageSubstitution $configOverrideSubstitution;

  /**
   * Default language substitution.
   */
  protected readonly DefaultLanguageSubstitution $defaultLanguageSubstitution;

  /**
   * String translation substitution.
   */
  protected readonly StringTranslationSubstitution $stringTranslationSubstitution;

  public function __construct(
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly LanguageDefault $languageDefault,
    protected readonly TranslationManager $translationManager,
  ) {
    $this->configOverrideSubstitution = new ConfigOverrideLanguageSubstitution($languageManager);
    $this->defaultLanguageSubstitution = new DefaultLanguageSubstitution($languageManager, $languageDefault);
    $this->stringTranslationSubstitution = new StringTranslationSubstitution($languageManager, $languageDefault, $translationManager);
  }

  /**
   * {@inheritdoc}
   */
  public function executeInEnvironment(EnvironmentParameter $param, callable $callback): mixed {
    $configOverrideSwitch = fn () => $this->configOverrideSubstitution->executeInEnvironment($param, $callback);
    $defaultLanguageSwitch = fn () => $this->defaultLanguageSubstitution->executeInEnvironment($param, $configOverrideSwitch);
    $translationSwitch = fn() => $this->stringTranslationSubstitution->executeInEnvironment($param, $defaultLanguageSwitch);
    return $translationSwitch();
  }

}
