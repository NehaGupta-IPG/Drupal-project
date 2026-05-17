<?php

namespace Drupal\Core\StringTranslation;

/**
 * Default implementation for the plural index.
 */
class FixedPluralIndex implements PluralIndexInterface {

  /**
   * {@inheritdoc}
   */
  public function getPluralIndex(int $count, ?string $langcode = NULL): int {
    return -1;
  }

}
