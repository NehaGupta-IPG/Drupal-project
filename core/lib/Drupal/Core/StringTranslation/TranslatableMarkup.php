<?php

namespace Drupal\Core\StringTranslation;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\ToStringTrait;

/**
 * Provides translatable markup class.
 *
 * This object, when cast to a string, will return the formatted, translated
 * string. Avoid casting it to a string yourself, because it is preferable to
 * let the rendering system do the cast as late as possible in the rendering
 * process, so that this object itself can be put, untranslated, into render
 * caches and thus the cache can be shared between different language contexts.
 *
 * @see \Drupal\Component\Render\FormattableMarkup
 * @see \Drupal\Core\StringTranslation\TranslationManager::translateString()
 * @see \Drupal\Core\Annotation\Translation
 */
class TranslatableMarkup extends FormattableMarkup {

  use ToStringTrait;

  /**
   * The translated markup without placeholder replacements.
   *
   * @var string
   */
  protected $translatedMarkup;

  /**
   * The translation options.
   *
   * @var array
   */
  protected $options;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  public function __construct($string, array $arguments = [], array $options = [], ?TranslationInterface $string_translation = NULL) {
    if (!is_string($string)) {
      $message = $string instanceof TranslatableMarkup ? '$string ("' . $string->getUntranslatedString() . '") must be a string.' : '$string ("' . (string) $string . '") must be a string.';
      throw new \InvalidArgumentException($message);
    }
    parent::__construct($string, $arguments);
    $this->options = $options;
    $this->stringTranslation = $string_translation;
  }

  /**
   * Gets the untranslated string value stored in this translated string.
   *
   * @return string
   *   The string stored in this wrapper.
   */
  public function getUntranslatedString() {
    return $this->string;
  }

  /**
   * Gets a specific option from this translated string.
   *
   * @param string $name
   *   Option name.
   *
   * @return mixed
   *   The value of this option or empty string of option is not set.
   */
  public function getOption($name) {
    return $this->options[$name] ?? '';
  }

  /**
   * Gets all options from this translated string.
   *
   * @return mixed[]
   *   The array of options.
   */
  public function getOptions() {
    return $this->options;
  }

  /**
   * Gets all arguments from this translated string.
   *
   * @return mixed[]
   *   The array of arguments.
   */
  public function getArguments() {
    return $this->arguments;
  }

  /**
   * Renders the object as a string.
   *
   * @return string
   *   The translated string.
   */
  public function render() {
    if (!isset($this->translatedMarkup)) {
      $this->translatedMarkup = $this->getStringTranslation()->translateString($this);
    }

    // Handle any replacements.
    if ($args = $this->getArguments()) {
      return $this->placeholderFormat($this->translatedMarkup, $args);
    }
    return $this->translatedMarkup;
  }

  /**
   * Magic __sleep() method to avoid serializing the string translator.
   */
  public function __sleep(): array {
    return ['string', 'arguments', 'options'];
  }

  /**
   * Gets the string translation service.
   *
   * @return \Drupal\Core\StringTranslation\TranslationInterface
   *   The string translation service.
   */
  protected function getStringTranslation() {
    if (!$this->stringTranslation) {
      $this->stringTranslation = \Drupal::service('string_translation');
    }

    return $this->stringTranslation;
  }

  /**
   * Returns the string length.
   *
   * @return int
   *   The length of the string.
   */
  public function count(): int {
    return mb_strlen($this->render());
  }

}
