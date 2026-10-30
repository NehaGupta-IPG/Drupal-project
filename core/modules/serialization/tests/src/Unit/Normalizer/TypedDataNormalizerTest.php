<?php

declare(strict_types=1);

namespace Drupal\Tests\serialization\Unit\Normalizer;

use Drupal\Core\Render\Markup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\Plugin\DataType\Any;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\serialization\Normalizer\TypedDataNormalizer;
use Drupal\Tests\serialization\Traits\JsonSchemaTestTrait;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\serialization\Normalizer\TypedDataNormalizer.
 */
#[CoversClass(TypedDataNormalizer::class)]
#[Group('serialization')]
class TypedDataNormalizerTest extends UnitTestCase {

  use JsonSchemaTestTrait;

  /**
   * The TypedDataNormalizer instance.
   *
   * @var \Drupal\serialization\Normalizer\TypedDataNormalizer
   */
  protected $normalizer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->normalizer = new TypedDataNormalizer();
  }

  /**
   * Tests the supportsNormalization() method.
   */
  public function testSupportsNormalization(): void {
    $this->assertTrue($this->normalizer->supportsNormalization($this->createStub(TypedDataInterface::class)));
    // Also test that an object not implementing TypedDataInterface fails.
    $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
  }

  /**
   * Tests the normalize() method.
   */
  public function testNormalize(): void {
    $typed_data = $this->createMock(TypedDataInterface::class);
    $typed_data->expects($this->once())
      ->method('getValue')
      ->willReturn('test');

    $this->assertEquals('test', $this->normalizer->normalize($typed_data));
  }

  /**
   * Tests the JSON Schema for a stringable value.
   */
  #[DataProvider('providerTestStringableSchema')]
  public function testStringableSchema(bool $required, array $expected): void {
    $typed_data = new Any(DataDefinition::createFromDataType('any')->setRequired($required));
    $typed_data->setValue(Markup::create('<p>Text</p>'));

    $this->assertSame($expected, $this->normalizer->normalize($typed_data, 'json_schema'));
  }

  /**
   * Data provider for ::testStringableSchema().
   */
  public static function providerTestStringableSchema(): array {
    return [
      'optional' => [FALSE, ['oneOf' => [['type' => 'string'], ['type' => 'null']]]],
      'required' => [TRUE, ['type' => 'string']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function jsonSchemaDataProvider(): array {
    $optional = new Any(DataDefinition::createFromDataType('any'));
    $optional->setValue(Markup::create('<p>Text</p>'));
    $required = new Any(DataDefinition::createFromDataType('any')->setRequired(TRUE));
    $required->setValue(Markup::create('<p>Text</p>'));
    return [
      'stringable' => [$optional],
      'stringable, required' => [$required],
    ];
  }

}
