<?php

declare(strict_types=1);

namespace Drupal\Tests\path_alias\Traits;

/**
 * Sets the path alias fallback language code for tests.
 */
trait PathAliasLanguageFallbackTestTrait {

  /**
   * Sets the fallback language code used for path alias.
   *
   * @param string|null $langcode
   *   The language code, or NULL to erase the previously configured value.
   */
  protected function setPathAliasFallbackLanguage(?string $langcode = NULL): void {
    \Drupal::state()
      ->set('path_alias_language_fallback_test.fallback_path_alias_alter.candidates', $langcode);
  }

}
