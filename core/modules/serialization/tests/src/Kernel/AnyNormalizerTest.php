<?php

namespace Drupal\Tests\serialization\Kernel;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\serialization\Normalizer\AnyNormalizer
 * @group typedData
 */
class AnyNormalizerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'serialization',
    'entity_test',
    'user',
  ];

  /**
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The typeDataManager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->serializer = \Drupal::service('serializer');
    $this->typedDataManager = \Drupal::typedDataManager();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
  }

  /**
   * Tests normalizing 'any' typed data with basic string stored.
   */
  public function testNormalizeBase(): void {
    $typed_data = $this->buildDataBasicString();
    $this->assertSame('test', $this->serializer->normalize($typed_data, 'json'));
  }

  /**
   * Tests normalizing 'any' typed data with traversable object stored.
   */
  public function testNormalizeTraversableObject(): void {
    $typed_data = $this->buildDataTraversableObject();
    $this->assertSame([
      'property1' => 'value1',
      'property2' => 'value2',
    ], $this->serializer->normalize($typed_data));
  }

  /**
   * Tests normalizing 'any' typed data with typed data stored.
   */
  public function testNormalizeTypedData(): void {
    $typed_data = $this->buildDataTypedData();
    $this->assertSame('test', $this->serializer->normalize($typed_data));
  }

  /**
   * Tests normalizing 'any' typed data with entity stored.
   */
  public function testNormalizeEntity(): void {
    $entity = EntityTest::create(['name' => $this->randomString()]);
    $entity->save();
    $normalized = $this->serializer->normalize($entity);
    $this->assertSame($entity->getName(), $normalized['name'][0]['value']);
  }

  /**
   * Tests normalizing 'any' typed data with non-normalizable object stored.
   */
  public function testNormalizeNonNormalizableObject(): void {
    $this->expectException(\UnexpectedValueException::class);
    $object = $this->buildDataNonNormalizableObject();
    $this->serializer->normalize($object);
  }

  /**
   * Tests normalizing 'any' typed data on more complex situation.
   */
  public function testNormalizeComplex(): void {
    $typed_data = $this->buildDataComplex();
    $this->assertSame([
      'key1' => 'test1',
      'key2' => 'test2',
      'key3' => [
        'property1' => 'value1',
        'property2' => 'value2',
      ],
    ], $this->serializer->normalize($typed_data));
  }

  /**
   * Builds example 'any' typed data with basic string stored.
   */
  protected function buildDataBasicString(): TypedDataInterface {
    return $this->typedDataManager->create(
      DataDefinition::create('any'),
      'test',
      'test name'
    );
  }

  /**
   * Builds example 'any' typed data with traversable object stored.
   */
  protected function buildDataTraversableObject(): TypedDataInterface {
    return $this->typedDataManager->create(
      DataDefinition::create('any'),
      new TraversableObject(),
      'test name'
    );
  }

  /**
   * Builds example 'any' typed data with typed data stored.
   */
  protected function buildDataTypedData(): TypedDataInterface {
    $typed_data_string = $this->typedDataManager->create(
      DataDefinition::create('string'),
      'test',
      'typed data string'
    );

    return $this->typedDataManager->create(
      DataDefinition::create('any'),
      $typed_data_string,
      'typed data any'
    );
  }

  /**
   * Builds example 'any' typed data with non-normalizable object stored.
   */
  protected function buildDataNonNormalizableObject(): TypedDataInterface {
    return $this->typedDataManager->create(
      DataDefinition::create('any'),
      new NonNormalizableObject(),
      'test name'
    );
  }

  /**
   * Builds example 'any' typed data with more complex data stored.
   */
  protected function buildDataComplex(): TypedDataInterface {
    $typed_data_string = $this->typedDataManager->create(
      DataDefinition::create('string'),
      'test2',
      'typed data string'
    );
    $traversableObject = new TraversableObject();
    $value = [
      'key1' => 'test1',
      'key2' => $typed_data_string,
      'key3' => $traversableObject,
    ];

    return $this->typedDataManager->create(
      DataDefinition::create('any'),
      $value,
      'typed data any'
    );
  }

}

/**
 * Build a non-normalizable object.
 */
class NonNormalizableObject {

  /**
   * The first property.
   *
   * @var string
   */
  public string $property1 = "value1";

  /**
   * The second property.
   *
   * @var string
   */
  public string $property2 = "value2";

}

/**
 * Build a traversable object.
 */
class TraversableObject extends NonNormalizableObject implements \IteratorAggregate {

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    return new \ArrayIterator($this);
  }

}
