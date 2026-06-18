<?php

declare(strict_types=1);

namespace Drupal\Tests\serialization\Unit\Normalizer;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\CreatedItem;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Field\Plugin\Field\FieldType\TimestampItem;
use Drupal\Core\Field\TypedData\FieldItemDataDefinitionInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\Core\TypedData\Plugin\DataType\Timestamp;
use Drupal\serialization\Normalizer\TimestampItemNormalizer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Serializer\Serializer;

/**
 * Tests that TimestampItem (de)normalization uses Timestamp (de)normalization.
 *
 * @see \Drupal\serialization\Normalizer\TimestampNormalizer
 */
#[CoversClass(TimestampItemNormalizer::class)]
#[Group('serialization')]
class TimestampItemNormalizerTest extends UnitTestCase {

  /**
   * The time stamp normalizer.
   *
   * @var \Drupal\serialization\Normalizer\TimestampItemNormalizer
   */
  protected $normalizer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->normalizer = new TimestampItemNormalizer();

    new Settings(['support_serialized_field_property_names' => FALSE]);
  }

  /**
   * Tests supports normalization.
   */
  public function testSupportsNormalization(): void {
    $timestamp_item = $this->createTimestampItemProphecy();
    $this->assertTrue($this->normalizer->supportsNormalization($timestamp_item->reveal()));

    $entity_ref_item = $this->prophesize(EntityReferenceItem::class);
    $this->assertFalse($this->normalizer->supportsNormalization($entity_ref_item->reveal()));
  }

  /**
   * Tests supports denormalization.
   */
  public function testSupportsDenormalization(): void {
    $timestamp_item = $this->createTimestampItemProphecy();
    $this->assertTrue($this->normalizer->supportsDenormalization($timestamp_item->reveal(), TimestampItem::class));

    // CreatedItem extends regular TimestampItem.
    $timestamp_item = $this->prophesize(CreatedItem::class);
    $this->assertTrue($this->normalizer->supportsDenormalization($timestamp_item->reveal(), TimestampItem::class));

    $entity_ref_item = $this->prophesize(EntityReferenceItem::class);
    $this->assertFalse($this->normalizer->supportsNormalization($entity_ref_item->reveal(), TimestampItem::class));
  }

  /**
   * Tests normalize.
   *
   * @see \Drupal\Tests\serialization\Unit\Normalizer\TimestampNormalizerTest
   */
  public function testNormalize(): void {
    // Mock TimestampItem @FieldType, which contains a Timestamp @DataType,
    // which has a DataDefinition.
    $data_definition = $this->prophesize(DataDefinitionInterface::class);
    $data_definition->isInternal()
      ->willReturn(FALSE)
      ->shouldBeCalled();
    $timestamp = $this->prophesize(Timestamp::class);
    $timestamp->getDataDefinition()
      ->willReturn($data_definition->reveal())
      ->shouldBeCalled();
    $timestamp = $timestamp->reveal();
    $timestamp_item = $this->createTimestampItemProphecy();
    $timestamp_item->getProperties(TRUE)
      ->willReturn(['value' => $timestamp])
      ->shouldBeCalled();

    // Mock Serializer service, to assert that the Timestamp @DataType
    // normalizer would be called.
    $timestamp_datetype_normalization = $this->randomMachineName();
    $serializer_prophecy = $this->prophesize(Serializer::class);
    // This is where \Drupal\serialization\Normalizer\TimestampNormalizer would
    // be called.
    $serializer_prophecy->normalize($timestamp, NULL, [])
      ->willReturn($timestamp_datetype_normalization)
      ->shouldBeCalled();

    $this->normalizer->setSerializer($serializer_prophecy->reveal());

    $normalized = $this->normalizer->normalize($timestamp_item->reveal());
    $this->assertSame(['value' => $timestamp_datetype_normalization, 'format' => \DateTime::RFC3339], $normalized);
  }

  /**
   * Tests denormalize.
   */
  public function testDenormalize(): void {
    $timestamp_item_normalization = [
      'value' => $this->randomMachineName(),
      'format' => \DateTime::RFC3339,
    ];
    $timestamp_data_denormalization = $this->randomMachineName();

    $parent = $this->prophesize(TraversableTypedDataInterface::class);

    // The following code ends up calling TimestampItem::schema() which is
    // static, so use the real field item class with mocked dependencies.
    $field_data_definition = $this->prophesize(FieldItemDataDefinitionInterface::class);
    $field_data_definition->getPropertyDefinitions()
      ->willReturn([])
      ->shouldBeCalled();

    $field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $field_storage_definition = $this->prophesize(FieldStorageDefinitionInterface::class);

    $field_definition->getFieldStorageDefinition()
      ->willReturn($field_storage_definition)
      ->shouldBeCalled();

    $field_data_definition->getFieldDefinition()
      ->willReturn($field_definition->reveal())
      ->shouldBeCalled();

    $timestamp_item = new TimestampItem($field_data_definition->reveal(), 'timestamp', $parent->reveal());

    $context = [
      'target_instance' => $timestamp_item,
      'datetime_allowed_formats' => [\DateTime::RFC3339],
    ];

    // Mock Serializer service, to assert that the Timestamp @DataType
    // denormalizer would be called.
    $serializer_prophecy = $this->prophesize(Serializer::class);
    // This is where \Drupal\serialization\Normalizer\TimestampNormalizer would
    // be called.
    $serializer_prophecy->denormalize($timestamp_item_normalization['value'], Timestamp::class, NULL, $context)
      ->willReturn($timestamp_data_denormalization)
      ->shouldBeCalled();

    $this->normalizer->setSerializer($serializer_prophecy->reveal());

    $denormalized = $this->normalizer->denormalize($timestamp_item_normalization, TimestampItem::class, NULL, $context);
    $this->assertInstanceOf(TimestampItem::class, $denormalized);
  }

  /**
   * Creates a TimestampItem prophecy.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Field\Plugin\Field\FieldType\TimestampItem>
   *   The TimestampItem prophecy.
   */
  protected function createTimestampItemProphecy(): ObjectProphecy {
    $timestamp_item = $this->prophesize(TimestampItem::class);
    $timestamp_item->getParent()
      ->willReturn(TRUE);

    return $timestamp_item;
  }

}
