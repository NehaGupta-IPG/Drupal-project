<?php

declare(strict_types=1);

namespace Drupal\Core\Environment;

use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Decorates the plain language switcher.
 */
class MultilingualSubstitutionDecorator {

  /**
   * The multilingual language switcher.
   */
  protected readonly MultilingualSubstitution $multilingual;

  public function __construct(
    #[AutowireDecorated]
    protected readonly SubstitutionInterface $monolingual,
    protected readonly LanguageManagerInterface $languageManager,
  ) {
    $this->multilingual = new MultilingualSubstitution($languageManager);
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
