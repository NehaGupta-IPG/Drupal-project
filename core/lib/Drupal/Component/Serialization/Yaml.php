<?php

namespace Drupal\Component\Serialization;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

/**
 * Provides a YAML serialization implementation using symfony/yaml.
 */
class Yaml implements SerializationInterface {

  /**
   * {@inheritdoc}
   */
  public static function encode($data) {
    try {
      // Set the indentation to 2 to match Drupal's coding standards.
      $yaml = new Dumper(2);
      return $yaml->dump($data, PHP_INT_MAX, 0, SymfonyYaml::DUMP_EXCEPTION_ON_INVALID_TYPE | SymfonyYaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
    catch (\Exception $e) {
      throw new InvalidDataTypeException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function decode($raw) {
    try {
      $yaml = new Parser();
      // Make sure we have a single trailing newline. A very simple config like
      // 'foo: bar' with no newline will fail to parse otherwise.
      return $yaml->parse(
        $raw,
        SymfonyYaml::PARSE_EXCEPTION_ON_INVALID_TYPE | SymfonyYaml::PARSE_CUSTOM_TAGS
      );
    }
    catch (\Exception $e) {
      throw new InvalidDataTypeException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getFileExtension() {
    return 'yml';
  }

  /**
   * Parses custom YAML tags and convert values to primitive types.
   *
   * Tag objects will be replaced by their raw value or, if a callback
   * exists for that tag it will be used.
   *
   * The only tag custom tag supported by default is the '!translate' tag.
   *
   * @param mixed $data
   *   Parsed yaml data
   * @param callable[] $tall_callbacks
   *   Optional callbacks for tag value, indexed by tag.
   * @param array $options
   *   Extra options to pass to tag callbacks.
   *
   * @return mixed
   *   Parsed data with parsed tag values.
   */
  public static function decodeTags($data, array $tag_callbacks = [], array $options = []) {
    // Replace 'translate' tags with proper translations.
    $tag_callbacks += [
      'translate' => [__CLASS__, 'tagTranslate']
    ];
    if (is_array($data)) {
      foreach ($data as $key => $value) {
        if (is_object($value) && $value instanceof TaggedValue) {
          if (isset($tag_callbacks[$value->getTag()])) {
            $data[$key] = call_user_func($tag_callbacks[$value->getTag()], $value->getValue(), $options);
          }
          else {
            $data[$key] = $value->getValue();
          }
        }
        elseif (is_array($value)) {
          $data[$key] = static::decodeTags($value, $tag_callbacks);
        }
      }
      return $data;
    }
    else {
      return $data;
    }
  }

  /**
   * Callback for 'translate' tag value.
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
   * @param string|array $value
   *   The tagged value representing a translatable string.
   * @param array $options
   *   Extra options to pass to the translation.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translatable string object.
   */
  public static function tagTranslate($value, $options = []) {
    if (is_array($value) && isset($value['string'])) {
      $string = $value['string'];
      unset($value['string']);
      $options += $value;
    } elseif (is_string($value)) {
      $string = $value;
    }
    else {
      throw new InvalidDataTypeException("Cannot parse the 'translate' tag. The tagged value is not a string nor has a 'string' element.");
    }

    return new TranslatableMarkup($string, [], $options);
  }

}
