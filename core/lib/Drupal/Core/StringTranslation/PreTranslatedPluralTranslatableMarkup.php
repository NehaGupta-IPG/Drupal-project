<?php

namespace Drupal\Core\StringTranslation;

/**
 * A plural translatable markup that has already been translated.
 */
class PreTranslatedPluralTranslatableMarkup extends PluralTranslatableMarkup {

  public function __construct($count, public readonly string $translatedString, array $args = [], array $options = [], ?TranslationInterface $string_translation = NULL) {
    parent::__construct($count, '', '', $args, $options, $string_translation);
  }

}
