<?php

namespace Drupal\Component\Serialization;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;

/**
 * Default serialization for serialized PHP.
 */
class PhpSerialize implements ObjectAwareSerializationInterface {

  /**
   * {@inheritdoc}
   */
  public static function encode($data) {
    return serialize($data);
  }

  /**
   * {@inheritdoc}
   */
  public static function decode($raw) {
    // Suppress warnings and errors from unserialize.
    $decoded = @unserialize($raw, ['allowed_classes' => TRUE]);

    // Check if unserialize returned FALSE.
    if ($decoded === FALSE && $raw !== 'b:0;') {
      $error = error_get_last();
      throw new InvalidDataTypeException('Failed to unserialize data: ' . ($error['message'] ?? 'Unknown error'));
    }

    return $decoded;
  }

  /**
   * {@inheritdoc}
   */
  public static function getFileExtension() {
    return 'serialized';
  }

}
