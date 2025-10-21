<?php

namespace Drupal\Component\Serialization;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
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
   * Decodes custom YAML tags.
   *
   * Tag objects will be replaced by their raw value or, if a class exists for
   * that tag, it will be used to construct an object from the value. For
   * example:
   * @code
   *   Yaml::decodeTags($data, ['translate' => TranslatableMarkup::class]);
   * @endcode
   * will replace any values tagged with 'translate' with a TranslatableMarkup
   * object.
   *
   * @param array $data
   *   Parsed YAML data.
   * @param class-string[] $tag_classes
   *   Optional classes to use to construct from tag values, indexed by tag.
   *
   * @return array
   *   Parsed data with decoded tag values.
   */
  public static function decodeTags(array $data, array $tag_classes = []): array {
    foreach ($data as $key => $value) {
      if ($value instanceof TaggedValue) {
        if (isset($tag_classes[$value->getTag()])) {
          $data_value = (array) $value->getValue();
          $data[$key] = new ($tag_classes[$value->getTag()])(...$data_value);
        }
        else {
          $data[$key] = $value->getValue();
        }
      }
      elseif (is_array($value)) {
        $data[$key] = static::decodeTags($value, $tag_classes);
      }
    }
    return $data;
  }

}
