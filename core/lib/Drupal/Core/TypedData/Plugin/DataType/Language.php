<?php

namespace Drupal\Core\TypedData\Plugin\DataType;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\TypedData;

/**
 * Defines the 'language' data type.
 *
 * The plain value of a language is the language object, i.e. an instance of
 * \Drupal\Core\Language\Language. For setting the value the language object or
 * the language code as string may be passed.
 */
#[DataType(
  id: "language",
  label: new TranslatableMarkup("Language"),
  description: new TranslatableMarkup("A language object.")
)]
class Language extends TypedData {

  /**
   * The id of the language.
   *
   * @var string|null
   */
  protected $id;

  /**
   * @var \Drupal\Core\Language\LanguageInterface|null
   */
  protected $language;

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Language\LanguageInterface|null
   *   The language object, or NULL if the language is not set.
   */
  public function getValue() {
    if (!isset($this->language) && $this->id) {
      $this->language = \Drupal::languageManager()->getLanguage($this->id);
    }
    return $this->language;
  }

  /**
   * Overrides TypedData::setValue().
   *
   * Both the langcode and the language object may be passed as value, or NULL
   * to unset the value. If a string is passed it is assumed to be the
   * langcode. The language object must be an instance of
   * \Drupal\Core\Language\LanguageInterface.
   *
   * @throws \InvalidArgumentException
   *   If the value input is neither a string, language object or NULL.
   */
  public function setValue($value, $notify = TRUE) {
    // Support passing language objects.
    if ($value instanceof LanguageInterface) {
      $this->id = $value->getId();
      $this->language = $value;
    }
    elseif (isset($value) && !is_string($value)) {
      throw new \InvalidArgumentException('Value is no valid langcode or language object.');
    }
    else {
      $this->id = $value;
      $this->language = NULL;
    }
    // Notify the parent of any changes.
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getString() {
    $language = $this->getValue();
    return $language ? $language->getName() : '';
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    if (isset($this->id)) {
      return $this->id;
    }
    elseif (isset($this->language)) {
      return $this->language->getId();
    }
  }

}
