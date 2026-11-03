<?php

declare(strict_types=1);

namespace Drupal\Tests\serialization\Unit\Normalizer;

use Drupal\Core\Render\Markup;
use Drupal\Core\Serialization\Attribute\JsonSchema;
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
   * Tests the JSON Schema for optional and required data.
   */
  #[DataProvider('providerTestJsonSchemaNullable')]
  public function testJsonSchemaNullable(TypedDataInterface $typed_data, array $expected): void {
    $this->assertSame($expected, $this->normalizer->normalize($typed_data, 'json_schema'));
  }

  /**
   * Data provider for ::testJsonSchemaNullable().
   */
  public static function providerTestJsonSchemaNullable(): array {
    $stringable = static function (bool $required): Any {
      $typed_data = new Any(DataDefinition::createFromDataType('any')->setRequired($required));
      $typed_data->setValue(Markup::create('<p>Text</p>'));
      return $typed_data;
    };
    $declared = static function (bool $required): Any {
      return new class(DataDefinition::createFromDataType('any')->setRequired($required)) extends Any {

        /**
         * {@inheritdoc}
         */
        #[JsonSchema(['type' => 'string'])]
        public function getValue() {
          return 'Text';
        }

      };
    };
    $not_nullable = ['type' => 'string'];
    $nullable = ['oneOf' => [$not_nullable, ['type' => 'null']]];
    return [
      'optional stringable value: nullable' => [$stringable(FALSE), $nullable],
      'required stringable value: not nullable' => [$stringable(TRUE), $not_nullable],
      'optional declared value: nullable' => [$declared(FALSE), $nullable],
      'required declared value: not nullable' => [$declared(TRUE), $not_nullable],
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
      // #[JsonSchema] states what the method can return, so a value that says it
      // can be NULL has to pass its own schema when it is NULL.
      'declares a null option' => [
        new class(DataDefinition::createFromDataType('any')) extends Any {

          /**
           * {@inheritdoc}
           */
          #[JsonSchema(['type' => 'string'])]
          #[JsonSchema(['type' => 'null'])]
          public function getValue() {
            return NULL;
          }

        },
      ],
      'declares null in a list of types' => [
        new class(DataDefinition::createFromDataType('any')) extends Any {

          /**
           * {@inheritdoc}
           */
          #[JsonSchema(['type' => ['string', 'null']])]
          public function getValue() {
            return NULL;
          }

        },
      ],
    ];
  }

}
