<?php

namespace Drupal\Tests\serialization\Kernel;

use Drupal\Core\TypedData\DataDefinition;
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
  public static $modules = ['system', 'serialization', 'entity_test', 'user'];

  /**
   * The serializer type.
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
  public function testNormalizeBase() {
    $typed_data = $this->buildDataBasicString();
    $this->assertSame('test', $this->serializer->normalize($typed_data, 'json'));
  }

  /**
   * Tests normalizing 'any' typed data with traversable object stored.
   */
  public function testNormalizeTraversableObject() {
    $typed_data = $this->buildDataTraversableObject();
    $this->assertSame([
      'property1' => 'value1',
      'property2' => 'value2',
    ], $this->serializer->normalize($typed_data));
  }

  /**
   * Tests normalizing 'any' typed data with typed data stored.
   */
  public function testNormalizeTypedData() {
    $typed_data = $this->buildDataTypedData();
    $this->assertSame('test', $this->serializer->normalize($typed_data));
  }

  /**
   * Tests normalizing 'any' typed data with entity stored.
   */
  public function testNormalizeEntity() {
    $entity = EntityTest::create(['name' => $this->randomString()]);
    $entity->save();
    $normalized = $this->serializer->normalize($entity);
    $this->assertSame($entity->getName(), $normalized['name'][0]['value']);
  }

  /**
   * Tests normalizing 'any' typed data with non-normalizable object stored.
   */
  public function testNormalizeNonNormalizableObject() {
    $this->setExpectedException(\UnexpectedValueException::class);
    $object = $this->buildDataNonNormalizableObject();
    $this->serializer->normalize($object);
  }

  /**
   * Tests normalizing 'any' typed data on more complex situation.
   */
  public function testNormalizeComplex() {
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
  protected function buildDataBasicString() {
    $typed_data = $this->typedDataManager->create(
      DataDefinition::create('any'),
      'test',
      'test name'
    );
    return $typed_data;
  }

  /**
   * Builds example 'any' typed data with traversable object stored.
   */
  protected function buildDataTraversableObject() {
    $typed_data = $this->typedDataManager->create(
      DataDefinition::create('any'),
      new TraversableObject(),
      'test name'
    );
    return $typed_data;
  }

  /**
   * Builds example 'any' typed data with typed data stored.
   */
  protected function buildDataTypedData() {
    $typed_data_string = $this->typedDataManager->create(
      DataDefinition::create('string'),
      'test',
      'typed data string'
    );
    $typed_data_any = $this->typedDataManager->create(
      DataDefinition::create('any'),
      $typed_data_string,
      'typed data any'
    );
    return $typed_data_any;
  }

  /**
   * Builds example 'any' typed data with non-normalizable object stored.
   */
  protected function buildDataNonNormalizableObject() {
    $typed_data = $this->typedDataManager->create(
      DataDefinition::create('any'),
      new NonNormalizableObject(),
      'test name'
    );
    return $typed_data;
  }

  /**
   * Builds example 'any' typed data with more complex data stored.
   */
  protected function buildDataComplex() {
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
    $typed_data_any = $this->typedDataManager->create(
      DataDefinition::create('any'),
      $value,
      'typed data any'
    );
    return $typed_data_any;
  }

}

/**
 * Build a non-normalizable object.
 */
class NonNormalizableObject {

  public $property1 = "value1";

  public $property2 = "value2";

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
