<?php

namespace Drupal\Component\Utility;

/**
 * Utility class for cryptographically-secure string handling routines.
 *
 * @ingroup utility
 */
class Crypt {

  /**
   * Generates a sha-256 hash using hmac.
   *
   * @param mixed $data
   *   Scalar value to be validated with the hmac.
   * @param mixed $key
   *   A secret key, this can be any scalar value.
   * @param bool $binary
   *   (optional) If TRUE ensures that the output will be raw binary data,
   *   otherwise the output will be a URL-safe hexadecimal string.
   *   Defaults to FALSE.
   *
   * @return string
   *   If $binary is TRUE, then a sha-256 hmac hexadecimal string is returned,
   *   otherwise the raw binary representation is returned.
   */
  public static function hmac($data, $key, bool $binary = FALSE) {
    // $data and $key being strings here is necessary to avoid empty string
    // results of the hash function if they are not scalar values. As this
    // function is used in security-critical contexts like token validation it
    // is important that it never returns an empty string.
    if (!is_scalar($data) || !is_scalar($key)) {
      throw new \InvalidArgumentException('Both parameters passed to \Drupal\Component\Utility\Crypt::hmac must be scalar values.');
    }

    return hash_hmac('sha256', $data, $key, $binary);
  }

  /**
   * Calculates a base-64 encoded, URL-safe sha-256 hmac.
   *
   * @param mixed $data
   *   Scalar value to be validated with the hmac.
   * @param mixed $key
   *   A secret key, this can be any scalar value.
   *
   * @return string
   *   A base-64 encoded sha-256 hmac, with + replaced with -, / with _ and
   *   any = padding characters removed.
   */
  public static function hmacBase64($data, $key) {
    return static::base64Encode(static::hmac($data, $key, TRUE));
  }

  /**
   * Generates a sha-256 hash.
   *
   * @param string $data
   *   String to be hashed.
   * @param bool $binary
   *   (optional) If TRUE ensures that the output will be raw binary data,
   *   otherwise the output will be a URL-safe hexadecimal string.
   *   Defaults to FALSE.
   *
   * @return string
   *   A sha-256 hash.
   */
  public static function hash($data, bool $binary = FALSE) {
    return hash('sha256', $data, $binary);
  }

  /**
   * Calculates a base-64 encoded, URL-safe sha-256 hash.
   *
   * @param string $data
   *   String to be hashed.
   *
   * @return string
   *   A base-64 encoded sha-256 hash, with + replaced with -, / with _ and
   *   any = padding characters removed.
   */
  public static function hashBase64($data) {
    return static::base64Encode(static::hash($data, TRUE));
  }

  /**
   * Returns a URL-safe, base64 encoded string of highly randomized bytes.
   *
   * @param int $count
   *   The number of random bytes to fetch and base64 encode.
   *
   * @return string
   *   A base-64 encoded string, with + replaced with -, / with _ and any =
   *   padding characters removed.
   */
  public static function randomBytesBase64($count = 32) {
    return static::base64Encode(random_bytes($count));
  }

  /**
   * Returns a URL-safe base64 encoded string.
   *
   * With + replaced with -, / with _ and any = padding characters removed.
   *
   * @param string $data
   *   String to be base64 encoded.
   *
   * @return string
   *   A base-64 encoded string, with + replaced with -, / with _ and any =
   *   padding characters removed.
   */
  public static function base64Encode($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
  }

}
