<?php

declare(strict_types=1);

namespace Drupal\language\Environment;

use Drupal\Core\Environment\EnvironmentParameter;
use Drupal\Core\Environment\SubstitutionInterface;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Decorates the plain language switcher.
 */
class MultilingualSubstitutionDecorator implements SubstitutionInterface {

  /**
   * The multilingual language switcher.
   */
  protected readonly MultilingualSubstitution $multilingual;

  public function __construct(
    #[AutowireDecorated]
    protected readonly SubstitutionInterface $monolingual,
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly LanguageDefault $languageDefault,
    #[Autowire(service: 'string_translation')]
    protected readonly TranslationManager $translationManager,
  ) {
    $this->multilingual = new MultilingualSubstitution($languageManager, $languageDefault, $translationManager);
  }

  /**
   * {@inheritdoc}
   */
  public function applies(EnvironmentParameter $param): bool {
    return $this->monolingual->applies($param) || $this->multilingual->applies($param);
  }

  /**
   * {@inheritdoc}
   */
  public function executeInEnvironment(EnvironmentParameter $param, callable $callback): mixed {
    if ($this->languageManager->isMultilingual()) {
      return $this->multilingual->executeInEnvironment($param, $callback);
    }
    else {
      return $this->monolingual->executeInEnvironment($param, $callback);
    }
  }

}
