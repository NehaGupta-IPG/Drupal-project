<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi\Kernel\Normalizer;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\jsonapi\Normalizer\FieldNormalizer;
use Drupal\Tests\jsonapi\Kernel\JsonapiKernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal\jsonapi\Normalizer\FieldNormalizer.
 *
 * @internal
 */
#[CoversClass(FieldNormalizer::class)]
#[Group('jsonapi')]
#[RunTestsInSeparateProcesses]
class FieldNormalizerTest extends JsonapiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'entity_test',
    'serialization',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $definitions = [];
    $definitions['single_annotated'] = BaseFieldDefinition::create('string')
      ->setLabel('Single annotated');
    $definitions['multiple_annotated'] = BaseFieldDefinition::create('string')
      ->setLabel('Multiple annotated')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $this->container->get('state')->set('entity_test.additional_base_field_definitions', $definitions);
    $this->container->get('entity_type.manager')->clearCachedDefinitions();
  }

  /**
   * Tests that annotations of a single-value field are kept on its schema.
   */
  public function testSingleValueSchemaKeepsAnnotations(): void {
    $schema = $this->getFieldSchema('single_annotated');
    $this->assertSame('default value', $schema['default']);
    $this->assertSame(['foo', 'bar'], $schema['examples']);
  }

  /**
   * Tests that annotations of a multi-value field describe its items only.
   *
   * A default value and example values declared on the item describe a single
   * item, not the list of items, so the list schema must not carry them.
   */
  public function testMultiValueSchemaMovesAnnotationsToItems(): void {
    $schema = $this->getFieldSchema('multiple_annotated');
    $this->assertSame('array', $schema['type']);
    $this->assertArrayNotHasKey('default', $schema);
    $this->assertArrayNotHasKey('examples', $schema);
    $this->assertSame('default value', $schema['items']['default']);
    $this->assertSame(['foo', 'bar'], $schema['items']['examples']);
  }

  /**
   * Returns the JSON schema of a field whose item declares annotations.
   *
   * @param string $field_name
   *   The name of the field on the test entity.
   *
   * @return array
   *   The JSON schema for the normalization of the field item list.
   */
  protected function getFieldSchema(string $field_name): array {
    $entity = EntityTest::create(['name' => 'Test entity']);
    $field = $entity->get($field_name);
    assert($field instanceof FieldItemListInterface);
    // The field is a string field, so its item schema is flattened to the
    // schema of the main property, which is where the annotations are
    // declared.
    $field->getItemDefinition()
      ->getPropertyDefinition('value')
      ->setDefault('default value')
      ->setExamples(['foo', 'bar']);
    $schema = $this->container->get('jsonapi.serializer')
      ->normalize($field, 'json_schema', ['account' => NULL]);
    $this->assertIsArray($schema);
    // The field is not required, so the schema also allows a null value.
    $this->assertArrayHasKey('oneOf', $schema);
    return $schema['oneOf'][0];
  }

}
