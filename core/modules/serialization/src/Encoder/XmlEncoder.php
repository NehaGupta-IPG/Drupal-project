<?php

namespace Drupal\serialization\Encoder;

use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder as BaseXmlEncoder;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

// cspell:ignore FFFD

/**
 * Adds XML support for serializer.
 *
 * This acts as a wrapper class for Symfony's XmlEncoder so that it is not
 * implementing NormalizationAwareInterface, and can be normalized externally.
 *
 * @internal
 *   This encoder should not be used directly. Rather, use the `serializer`
 *   service.
 */
class XmlEncoder implements SerializerAwareInterface, EncoderInterface, DecoderInterface {

  use SerializerAwareTrait;

  /**
   * The formats that this Encoder supports.
   *
   * @var array
   */
  protected static $format = ['xml'];

  /**
   * An instance of the Symfony XmlEncoder to perform the actual encoding.
   *
   * @var \Symfony\Component\Serializer\Encoder\XmlEncoder
   */
  protected $baseEncoder;

  /**
   * Gets the base encoder instance.
   *
   * @return \Symfony\Component\Serializer\Encoder\XmlEncoder
   *   The base encoder.
   */
  public function getBaseEncoder() {
    if (!isset($this->baseEncoder)) {
      $this->baseEncoder = new BaseXmlEncoder();
      $this->baseEncoder->setSerializer($this->serializer);
    }

    return $this->baseEncoder;
  }

  /**
   * Sets the base encoder instance.
   *
   * @param \Symfony\Component\Serializer\Encoder\XmlEncoder $encoder
   *   The XML encoder.
   */
  public function setBaseEncoder($encoder) {
    $this->baseEncoder = $encoder;
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data, $format, array $context = []): string {
    $sanitized_data = $this->sanitizeDataForXml($data);

    return $this->getBaseEncoder()->encode($sanitized_data, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding(string $format, array $context = []): bool {
    return in_array($format, static::$format);
  }

  /**
   * {@inheritdoc}
   */
  public function decode($data, $format, array $context = []): mixed {
    return $this->getBaseEncoder()->decode($data, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDecoding(string $format, array $context = []): bool {
    return in_array($format, static::$format);
  }

  /**
   * Recursively sanitizes data before XML encoding.
   *
   * This ensures that all string values are stripped of characters that are
   * not allowed in XML 1.0 before they are written to the XML output.
   *
   * @param mixed $data
   *   The data to sanitize.
   *
   * @return mixed
   *   The sanitized data.
   *
   * @see ::sanitizeXmlString()
   */
  private function sanitizeDataForXml(mixed $data): mixed {
    if (is_array($data)) {
      foreach ($data as $key => $value) {
        $data[$key] = $this->sanitizeDataForXml($value);
      }

      return $data;
    }

    if (is_string($data)) {
      return $this->sanitizeXmlString($data);
    }

    return $data;
  }

  /**
   * Strips characters that are not allowed in XML 1.0.
   *
   * XML 1.0 only allows the following character ranges:
   * - #x9 (tab), #xA (line feed), #xD (carriage return)
   * - #x20-#xD7FF
   * - #xE000-#xFFFD
   * - #x10000-#x10FFFF
   *
   * Any characters outside these ranges make the XML invalid and must be
   * removed prior to encoding to ensure consumers can parse the response.
   *
   * @param string $value
   *   The string to sanitize.
   *
   * @return string
   *   The sanitized string.
   *
   * @see http://www.w3.org/TR/xml/#charsets
   * @see https://www.drupal.org/project/drupal/issues/3549269
   */
  private function sanitizeXmlString(string $value): string {
    // First remove ASCII control characters that are not allowed in XML 1.0.
    // This works reliably even if the string is not valid UTF-8.
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);

    // Then remove any remaining characters that are outside the XML 1.0
    // allowed ranges. The /u modifier ensures the pattern is evaluated in
    // UTF-8 mode so the Unicode ranges behave as expected. If the string is
    // not valid UTF-8, preg_replace() returns NULL and we fall back to the
    // best-effort result from the first pass.
    $sanitized = preg_replace(
      '/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
      '',
      $value
    );

    return is_string($sanitized) ? $sanitized : (string) $value;
  }

}
