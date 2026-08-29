<?php

declare(strict_types=1);

namespace Drupal\Component\Serialization;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;

/**
 * Uses igbinary when available while remaining compatible with PHP serialize().
 */
class IgbinarySerialize implements ObjectAwareSerializationInterface {

  /**
   * Prefix used to distinguish igbinary payloads from legacy serialized data.
   */
  protected const IGBINARY_PREFIX = "\x00DRUPAL:igbinary\x00";

  /**
   * {@inheritdoc}
   */
  public static function encode($data) {
    static $is_igbinary_serialize = function_exists('igbinary_serialize');

    if (!$is_igbinary_serialize) {
      return static::executeEncoding(static fn() => serialize($data));
    }

    $encoded = static::executeEncoding(static fn() => igbinary_serialize($data));

    if ($encoded === NULL) {
      throw new InvalidDataTypeException('Igbinary serialization failed.');
    }

    return static::IGBINARY_PREFIX . $encoded;
  }

  /**
   * {@inheritdoc}
   */
  public static function decode($raw) {
    if (!str_starts_with($raw, static::IGBINARY_PREFIX)) {
      return static::executeDecoding(
        static fn() => unserialize($raw),
        [
          'unserialize(): Error at offset',
          'unserialize(): Extra data starting at offset',
          'unserialize(): Unexpected end of serialized data',
        ],
      );
    }
    static $is_igbinary_unserialize = function_exists('igbinary_unserialize');

    if (!$is_igbinary_unserialize) {
      throw new InvalidDataTypeException('The igbinary extension is required to decode igbinary cache data.');
    }

    return static::executeDecoding(
      static fn() => igbinary_unserialize(substr($raw, strlen(static::IGBINARY_PREFIX))),
      [
        'igbinary_unserialize_',
        'igbinary_unserialize:',
      ],
    );
  }

  /**
   * Converts thrown encoding failures into typed exceptions.
   *
   * @param callable $callback
   *   The encoding callback to execute.
   *
   * @return mixed
   *   The callback result.
   *
   * @throws \Drupal\Component\Serialization\Exception\InvalidDataTypeException
   *   Thrown when the serializer throws.
   */
  protected static function executeEncoding(callable $callback) {
    try {
      return $callback();
    }
    catch (\Throwable $exception) {
      throw new InvalidDataTypeException($exception->getMessage(), (int) $exception->getCode(), $exception);
    }
  }

  /**
   * Converts serializer decode warnings and errors into typed exceptions.
   *
   * @param callable $callback
   *   The decoding callback to execute.
   * @param string[] $failure_warning_prefixes
   *   Warning prefixes that indicate malformed serialized data.
   *
   * @return mixed
   *   The callback result.
   *
   * @throws \Drupal\Component\Serialization\Exception\InvalidDataTypeException
   *   Thrown when decode fails.
   */
  protected static function executeDecoding(callable $callback, array $failure_warning_prefixes) {
    set_error_handler(static function (int $severity, string $message) use ($failure_warning_prefixes): bool {
      foreach ($failure_warning_prefixes as $prefix) {
        if (str_starts_with($message, $prefix)) {
          throw new InvalidDataTypeException($message, $severity);
        }
      }

      return FALSE;
    }, E_WARNING);

    try {
      return $callback();
    }
    catch (InvalidDataTypeException $exception) {
      throw $exception;
    }
    catch (\Throwable $exception) {
      throw new InvalidDataTypeException($exception->getMessage(), (int) $exception->getCode(), $exception);
    }
    finally {
      restore_error_handler();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getFileExtension() {
    return 'serialized';
  }

}
