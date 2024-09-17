<?php

namespace Drupal\Component\Serialization;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;

/**
 * Default serialization for JSON.
 *
 * @ingroup third_party
 */
class Json implements SerializationInterface {

  /**
   * {@inheritdoc}
   *
   * Uses HTML-safe strings, with several characters escaped.
   */
  public static function encode($variable) {
    try {
      // Encode <, >, ', &, and ".
      return json_encode($variable, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    }
    catch (\JsonException $e) {
      throw new InvalidDataTypeException('JSON encoding failed: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function decode($string) {
    try {
      // Decode with error throwing enabled.
      return json_decode($string, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new InvalidDataTypeException('JSON decoding failed: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getFileExtension() {
    return 'json';
  }

}
