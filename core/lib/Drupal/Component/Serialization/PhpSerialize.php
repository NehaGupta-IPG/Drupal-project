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
    $error = NULL;
    set_error_handler(static function (int $errno, string $errstr) use (&$error): bool {
      $error = $errstr;
      return TRUE;
    });
    $decoded = unserialize($raw, ['allowed_classes' => TRUE]);
    restore_error_handler();

    if ($decoded === FALSE && $raw !== serialize(FALSE)) {
      throw new InvalidDataTypeException($error ?? 'Failed to unserialize data');
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
