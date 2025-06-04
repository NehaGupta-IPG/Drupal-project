<?php

namespace Drupal\Core\StringTranslation;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Provides a callback for YAML 'translate' tags.
 */
class YamlTagTranslator {

  /**
   * Callback for 'translate' tag object.
   *
   * A translatable string can be represented as a plain string, or as an array
   * with 'string' and 'context' and maybe other parameters. For example:
   *
   * @code
   * element:
   *   label: !translate 'Website feedback'
   * another:
   *   label: !translate {string: 'Website feedback', context: 'Contact form'}
   * @endcode
   *
   * In this second case, the full array minus the 'string' part will be passed
   * as the '$options' parameter for the translation.
   *
   * @see t()
   *
   * @param \Symfony\Component\Yaml\Tag\TaggedValue $tagged_value
   *   The tagged value object.
   * @param array $options
   *   Extra options to pass to the translation.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translatable string object.
   */
  public static function translateTaggedValue(TaggedValue $tagged_value, $options = []) {
    $value = $tagged_value->getValue();
    if (is_array($value) && isset($value['string'])) {
      $string = $value['string'];
      unset($value['string']);
      $options += $value;
    }
    elseif (is_string($value)) {
      $string = $value;
    }
    else {
      throw new InvalidDataTypeException("Cannot parse the 'translate' tag. The tagged value is not a string nor has a 'string' element.");
    }

    return new TranslatableMarkup($string, [], $options);
  }

}
