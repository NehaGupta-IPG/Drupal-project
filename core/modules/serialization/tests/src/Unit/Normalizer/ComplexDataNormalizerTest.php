<?php

declare(strict_types=1);

namespace Drupal\Tests\serialization\Unit\Normalizer;

use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\serialization\Normalizer\ComplexDataNormalizer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Serializer\Serializer;

/**
 * Tests Drupal\serialization\Normalizer\ComplexDataNormalizer.
 */
#[CoversClass(ComplexDataNormalizer::class)]
#[Group('serialization')]
class ComplexDataNormalizerTest extends UnitTestCase {

  use InternalTypedDataTestTrait;

  /**
   * Test format string.
   *
   * @var string
   */
  const TEST_FORMAT = 'test_format';

  /**
   * The Complex data normalizer under test.
   *
   * @var \Drupal\serialization\Normalizer\ComplexDataNormalizer
   */
  protected $normalizer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->normalizer = new ComplexDataNormalizer();
  }

  /**
   * Tests supports normalization.
   */
  public function testSupportsNormalization(): void {
    $complex_data = $this->prophesize(ComplexDataInterface::class)->reveal();
    $this->assertTrue($this->normalizer->supportsNormalization($complex_data));
    // Also test that an object not implementing ComplexDataInterface fails.
    $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
  }

  /**
   * Tests normalizing complex data.
   */
  public function testNormalizeComplexData(): void {
    $serializer_prophecy = $this->prophesize(Serializer::class);

    $non_internal_property = $this->prophesize(TypedDataInterface::class)->reveal();

    $serializer_prophecy->normalize($non_internal_property, static::TEST_FORMAT, [])
      ->willReturn('A-normalized')
      ->shouldBeCalled();

    $this->normalizer->setSerializer($serializer_prophecy->reveal());

    $complex_data = $this->prophesize(ComplexDataInterface::class);
    $complex_data->getProperties(TRUE)
      ->willReturn([
        'prop:a' => $non_internal_property,
        'prop:internal' => $this->prophesize(TypedDataInterface::class)->reveal(),
      ])
      ->shouldBeCalled();

    $prop_a_definition = $this->prophesize(DataDefinitionInterface::class);
    $prop_a_definition->isInternal()
      ->willReturn(FALSE)
      ->shouldBeCalled();
    $prop_internal_definition = $this->prophesize(DataDefinitionInterface::class);
    $prop_internal_definition->isInternal()
      ->willReturn(TRUE)
      ->shouldBeCalled();

    $complex_data_definition = $this->prophesize(ComplexDataDefinitionInterface::class);
    $complex_data_definition->getPropertyDefinitions()
      ->willReturn([
        'prop:a' => $prop_a_definition->reveal(),
        'prop_internal' => $prop_internal_definition->reveal(),
      ])
      ->shouldBeCalled();
    $complex_data->getDataDefinition()
      ->willReturn($complex_data_definition->reveal())
      ->shouldBeCalled();

    $complex_data->get('prop:a')
      ->willReturn($non_internal_property)
      ->shouldBeCalled();

    $normalized = $this->normalizer->normalize($complex_data->reveal(), static::TEST_FORMAT);
    $this->assertEquals(['prop:a' => 'A-normalized'], $normalized);
  }

  /**
   * Tests normalize() where $object does not implement ComplexDataInterface.
   *
   * Normalizers extending ComplexDataNormalizer may have a different supported
   * class.
   */
  public function testNormalizeNonComplex(): void {
    $normalizer = new TestExtendedNormalizer();
    $serialization_context = ['test' => 'test'];

    $serializer_prophecy = $this->prophesize(Serializer::class);
    $serializer_prophecy->normalize('A', static::TEST_FORMAT, $serialization_context)
      ->willReturn('A-normalized')
      ->shouldBeCalled();
    $serializer_prophecy->normalize('B', static::TEST_FORMAT, $serialization_context)
      ->willReturn('B-normalized')
      ->shouldBeCalled();

    $normalizer->setSerializer($serializer_prophecy->reveal());

    $stdClass = new \stdClass();
    $stdClass->a = 'A';
    $stdClass->b = 'B';

    $normalized = $normalizer->normalize($stdClass, static::TEST_FORMAT, $serialization_context);
    $this->assertEquals(['a' => 'A-normalized', 'b' => 'B-normalized'], $normalized);

  }

}

/**
 * Test normalizer with a different supported class.
 */
class TestExtendedNormalizer extends ComplexDataNormalizer {

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      \stdClass::class => TRUE,
    ];
  }

}
