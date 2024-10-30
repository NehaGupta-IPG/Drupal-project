<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Serialization;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\PhpSerialize;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for the PhpSerialize class.
 *
 * @group serialization
 */
class PhpSerializeTest extends TestCase {

  /**
   * Tests basic PHP serialization and deserialization.
   */
  public function testPhpSerialization(): void {
    $data = ['name' => 'Bob', 'age' => 25];
    $encoded = PhpSerialize::encode($data);
    $decoded = PhpSerialize::decode($encoded);

    $this->assertEquals($data, $decoded);
  }

  /**
   * Tests handling of malformed serialized data.
   *
   * @throws \Drupal\Component\Serialization\Exception\InvalidDataTypeException
   */
  public function testInvalidPhpUnserialization(): void {
    $invalidSerializedData = 'O:8:"stdClass":1:{s:4:"name";s:3:"Bob";';

    $this->expectException(InvalidDataTypeException::class);
    PhpSerialize::decode($invalidSerializedData);
  }

  /**
   * Tests deserialization of serialized boolean FALSE.
   */
  public function testSerializedFalse(): void {
    $data = serialize(FALSE);

    $decoded = PhpSerialize::decode($data);
    $this->assertFalse($decoded);
  }

}
