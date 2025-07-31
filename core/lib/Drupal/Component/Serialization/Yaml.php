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
   * Parses custom YAML tags and convert values to primitive types.
   *
   * Tag objects will be replaced by their raw value or, if a callback
   * exists for that tag it will be used.
   *
   * The only custom tag supported by default is the '!translate' tag.
   *
   * @param mixed $data
   *   Parsed YAML data.
   * @param callable[] $tag_callbacks
   *   Optional callbacks for tag value, indexed by tag.
   *
   * @return mixed
   *   Parsed data with parsed tag values.
   */
  public static function decodeTags(mixed $data, array $tag_callbacks = []): mixed {
    if (is_array($data)) {
      foreach ($data as $key => $value) {
        if (is_object($value) && $value instanceof TaggedValue) {
          if (isset($tag_callbacks[$value->getTag()])) {
            $data[$key] = call_user_func($tag_callbacks[$value->getTag()], $value);
          }
          else {
            $data[$key] = $value->getValue();
          }
        }
        elseif (is_array($value)) {
          $data[$key] = static::decodeTags($value, $tag_callbacks);
        }
      }
    }
    return $data;
  }

}
