<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Kernel\Number;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the decimal and integer number formatters.
 */
#[Group('field')]
#[RunTestsInSeparateProcesses]
class NumericFormatterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'entity_test',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['field']);
    $this->installEntitySchema('entity_test');
  }

  /**
   * Renders fields of a given entity with a given display.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity object with attached fields to render.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The display to render the fields in.
   *
   * @return string
   *   The rendered entity fields.
   */
  protected function renderEntityFields(FieldableEntityInterface $entity, EntityViewDisplayInterface $display): string {
    $content = $display->build($entity);
    return $this->render($content);
  }

  /**
   * Tests that a NULL value does not reach number_format().
   *
   * @param string $field_type
   *   The field type to test, 'decimal' or 'integer'.
   * @param string $formatter_type
   *   The formatter plugin id to test.
   */
  #[DataProvider('providerFieldTypes')]
  public function testNullValueDoesNotError(string $field_type, string $formatter_type): void {
    $field_name = $this->randomMachineName();
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => $field_type,
    ])->save();
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();

    $display = \Drupal::service('entity_display.repository')
      ->getViewDisplay('entity_test', 'entity_test')
      ->setComponent($field_name, ['type' => $formatter_type]);
    $display->save();

    // An entity whose field item exists but whose value is NULL, as happens
    // when an optional numeric field is left blank. Rendering this must not
    // pass NULL into number_format(), which triggers a deprecation notice in
    // PHP 8.1+ that core's test suite treats as a failure.
    $entity = EntityTest::create([]);
    $entity->{$field_name}->value = NULL;

    $output = $this->renderEntityFields($entity, $display);
    $this->assertStringNotContainsString('number_format', $output);
  }

  /**
   * Data provider for testNullValueDoesNotError().
   */
  public static function providerFieldTypes(): array {
    return [
      'decimal' => ['decimal', 'number_decimal'],
      'integer' => ['integer', 'number_integer'],
    ];
  }

}
