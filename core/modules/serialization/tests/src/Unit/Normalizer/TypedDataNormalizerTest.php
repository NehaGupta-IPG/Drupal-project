<?php

declare(strict_types=1);

namespace Drupal\Tests\serialization\Unit\Normalizer;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\serialization\Normalizer\TypedDataNormalizer;
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
   * Tests that a declared default value is annotated on the JSON schema.
   */
  public function testNormalizeJsonSchemaDefault(): void {
    $definition = DataDefinition::createFromDataType('string');
    $definition->setDefault('test');
    $typed_data = $this->createStub(TypedDataInterface::class);
    $typed_data->method('getValue')->willReturn('test');
    $typed_data->method('getDataDefinition')->willReturn($definition);

    $schema = $this->normalizer->normalize($typed_data, 'json_schema');
    $this->assertIsArray($schema);
    $this->assertSame('test', $schema['default']);
  }

  /**
   * Tests that no default is annotated when the definition declares none.
   */
  public function testNormalizeJsonSchemaWithoutDefault(): void {
    $typed_data = $this->createStub(TypedDataInterface::class);
    $typed_data->method('getValue')->willReturn('test');
    $typed_data->method('getDataDefinition')
      ->willReturn(DataDefinition::createFromDataType('string'));

    $schema = $this->normalizer->normalize($typed_data, 'json_schema');
    $this->assertIsArray($schema);
    $this->assertArrayNotHasKey('default', $schema);
  }

  /**
   * Tests that an array default value is annotated at any depth.
   */
  public function testNormalizeJsonSchemaArrayDefault(): void {
    $definition = DataDefinition::createFromDataType('any');
    $definition->setDefault([
      'count' => 1,
      'nested' => ['label' => new FormattableMarkup('@greeting world', ['@greeting' => 'Hello'])],
    ]);

    $schema = $this->normalizeWithDefinition($definition);
    $this->assertSame(['count' => 1, 'nested' => ['label' => 'Hello world']], $schema['default']);
  }

  /**
   * Data provider for ::testNormalizeJsonSchemaUnsupportedDefault().
   */
  public static function providerUnsupportedDefault(): array {
    return [
      'object' => [new \stdClass()],
      'nested object' => [['one' => new \stdClass()]],
      'not a number' => [NAN],
      'infinite' => [INF],
      'invalid UTF-8' => ["\xB1\x31"],
      'nested invalid UTF-8' => [['one' => "\xB1\x31"]],
    ];
  }

  /**
   * Tests that defaults which cannot be encoded as JSON are not annotated.
   */
  #[DataProvider('providerUnsupportedDefault')]
  public function testNormalizeJsonSchemaUnsupportedDefault(mixed $default): void {
    $definition = DataDefinition::createFromDataType('any');
    $definition->setDefault($default);

    $schema = $this->normalizeWithDefinition($definition);
    $this->assertArrayNotHasKey('default', $schema);
    $this->assertIsString(json_encode($schema, JSON_THROW_ON_ERROR));
  }

  /**
   * Tests that declared example values are annotated on the JSON schema.
   */
  public function testNormalizeJsonSchemaExamples(): void {
    $definition = DataDefinition::createFromDataType('string');
    $definition->setExamples(['foo', 'bar']);

    $schema = $this->normalizeWithDefinition($definition);
    $this->assertSame(['foo', 'bar'], $schema['examples']);
  }

  /**
   * Tests that no examples are annotated when the definition declares none.
   */
  public function testNormalizeJsonSchemaWithoutExamples(): void {
    $schema = $this->normalizeWithDefinition(DataDefinition::createFromDataType('string'));
    $this->assertArrayNotHasKey('examples', $schema);
  }

  /**
   * Tests that an array example value is annotated at any depth.
   */
  public function testNormalizeJsonSchemaArrayExample(): void {
    $definition = DataDefinition::createFromDataType('any');
    $definition->setExamples([
      [
        'count' => 1,
        'nested' => ['label' => new FormattableMarkup('@greeting world', ['@greeting' => 'Hello'])],
      ],
    ]);

    $schema = $this->normalizeWithDefinition($definition);
    $this->assertSame([['count' => 1, 'nested' => ['label' => 'Hello world']]], $schema['examples']);
  }

  /**
   * Tests that an array example is dropped in full when a nested value fails.
   */
  public function testNormalizeJsonSchemaUnsupportedNestedExample(): void {
    $definition = DataDefinition::createFromDataType('any');
    $definition->setExamples([
      'foo',
      ['one' => 'representable', 'two' => ['three' => new \stdClass()]],
      'bar',
    ]);

    $schema = $this->normalizeWithDefinition($definition);
    $this->assertSame(['foo', 'bar'], $schema['examples']);
    $this->assertIsString(json_encode($schema, JSON_THROW_ON_ERROR));
  }

  /**
   * Tests that a default value and examples are annotated together.
   */
  public function testNormalizeJsonSchemaDefaultAndExamples(): void {
    $definition = DataDefinition::createFromDataType('string');
    $definition->setDefault('test')->setExamples(['foo', 'bar']);

    $schema = $this->normalizeWithDefinition($definition);
    $this->assertSame('test', $schema['default']);
    $this->assertSame(['foo', 'bar'], $schema['examples']);
  }

  /**
   * Tests that examples are annotated when the default cannot be encoded.
   */
  public function testNormalizeJsonSchemaUnsupportedDefaultWithExamples(): void {
    $definition = DataDefinition::createFromDataType('any');
    $definition->setDefault(new \stdClass())->setExamples(['foo', 'bar']);

    $schema = $this->normalizeWithDefinition($definition);
    $this->assertArrayNotHasKey('default', $schema);
    $this->assertSame(['foo', 'bar'], $schema['examples']);
  }

  /**
   * Normalizes typed data with the given definition to its JSON schema.
   *
   * @param \Drupal\Core\TypedData\DataDefinition $definition
   *   The data definition of the typed data object being normalized.
   *
   * @return array
   *   The JSON schema for the normalization.
   */
  protected function normalizeWithDefinition(DataDefinition $definition): array {
    $typed_data = $this->createStub(TypedDataInterface::class);
    $typed_data->method('getValue')->willReturn('test');
    $typed_data->method('getDataDefinition')->willReturn($definition);

    $schema = $this->normalizer->normalize($typed_data, 'json_schema');
    $this->assertIsArray($schema);
    return $schema;
  }

}
