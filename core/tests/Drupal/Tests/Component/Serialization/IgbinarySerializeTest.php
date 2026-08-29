<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Serialization;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\IgbinarySerialize;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests Drupal\Component\Serialization\IgbinarySerialize.
 */
#[CoversClass(IgbinarySerialize::class)]
#[Group('Serialization')]
class IgbinarySerializeTest extends TestCase {

  /**
   * Tests igbinary encoding and decoding when the extension is available.
   */
  public function testEncodeDecodeWithIgbinary(): void {
    if (!function_exists('igbinary_serialize')) {
      $this->markTestSkipped('Requires the igbinary extension.');
    }

    $data = [
      'foo' => 'bar',
      'nested' => ['baz' => TRUE],
      'object' => (object) ['qux' => 'quux'],
    ];

    $encoded = IgbinarySerialize::encode($data);

    $this->assertNotSame(serialize($data), $encoded);
    $this->assertEquals($data, IgbinarySerialize::decode($encoded));
  }

  /**
   * Tests that encoding falls back to PHP serialization when needed.
   */
  public function testEncodeDecodeFallsBackWithoutIgbinary(): void {
    $data = [
      'foo' => 'bar',
      'nested' => ['baz' => TRUE],
      'object' => (object) ['qux' => 'quux'],
    ];

    $encoded = TestIgbinarySerializeWithoutExtension::encode($data);

    $this->assertSame(serialize($data), $encoded);
    $this->assertEquals($data, TestIgbinarySerializeWithoutExtension::decode($encoded));
  }

  /**
   * Tests that legacy PHP-serialized data remains readable.
   */
  public function testDecodeLegacyPhpSerializedData(): void {
    $data = [
      'foo' => 'bar',
      'nested' => ['baz' => TRUE],
      'object' => (object) ['qux' => 'quux'],
    ];

    $this->assertEquals($data, IgbinarySerialize::decode(serialize($data)));
  }

  /**
   * Tests that invalid values raise typed exceptions on encode.
   */
  public function testEncodeInvalidDataThrowsException(): void {
    $this->expectException(InvalidDataTypeException::class);
    $this->expectExceptionMessage("Serialization of 'Closure' is not allowed");

    IgbinarySerialize::encode(static fn() => NULL);
  }

  /**
   * Tests that invalid legacy payloads fail with typed exceptions.
   */
  public function testDecodeInvalidLegacyPhpSerializedDataThrowsException(): void {
    $this->expectException(InvalidDataTypeException::class);

    IgbinarySerialize::decode('not serialized');
  }

  /**
   * Tests that invalid igbinary payloads fail with typed exceptions.
   */
  public function testDecodeInvalidIgbinaryPayloadThrowsException(): void {
    if (!function_exists('igbinary_unserialize')) {
      $this->markTestSkipped('Requires the igbinary extension.');
    }

    $this->expectException(InvalidDataTypeException::class);

    IgbinarySerialize::decode(TestIgbinarySerializeWithoutExtension::getIgbinaryPrefix() . 'not igbinary');
  }

  /**
   * Tests that igbinary payloads fail clearly without the extension.
   */
  public function testDecodeIgbinaryPayloadWithoutExtension(): void {
    if (!function_exists('igbinary_serialize')) {
      $this->markTestSkipped('Requires the igbinary extension.');
    }

    $encoded = IgbinarySerialize::encode(['foo' => 'bar']);
    $this->expectException(InvalidDataTypeException::class);
    $this->expectExceptionMessage('The igbinary extension is required to decode igbinary cache data.');

    TestIgbinarySerializeWithoutExtension::decode($encoded);
  }

  /**
   * Tests the file extension.
   */
  public function testGetFileExtension(): void {
    $this->assertSame('serialized', IgbinarySerialize::getFileExtension());
  }

}

/**
 * Test-only serializer variant that emulates the no-extension code path.
 */
class TestIgbinarySerializeWithoutExtension extends IgbinarySerialize {

  /**
   * {@inheritdoc}
   */
  public static function encode($data) {
    return static::executeEncoding(static fn() => serialize($data));
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

    throw new InvalidDataTypeException('The igbinary extension is required to decode igbinary cache data.');
  }

  /**
   * Exposes the igbinary prefix for test inputs.
   */
  public static function getIgbinaryPrefix(): string {
    return static::IGBINARY_PREFIX;
  }

}
