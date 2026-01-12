<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Handler;

use Drupal\Component\Utility\Html;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the core Drupal\views\Plugin\views\field\Serialized handler.
 *
 * @see \Drupal\views\Plugin\views\field\Serialized
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class FieldSerializedTest extends ViewsKernelTestBase {

  /**
   * Views used by this test.
   *
   * @var string[]
   */
  public static $testViews = ['test_view'];

  /**
   * {@inheritdoc}
   */
  protected function schemaDefinition(): array {
    $schema = parent::schemaDefinition();

    $schema['views_test_data']['fields']['data'] = [
      'description' => 'Serialized data',
      'type' => 'blob',
      'size' => 'big',
      'serialize' => TRUE,
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function viewsData(): array {
    $data = parent::viewsData();

    $data['views_test_data']['data'] = [
      'title' => 'Data',
      'help' => 'Serialized data field',
      'field' => [
        'id' => 'serialized',
      ],
    ];

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function dataSet(): array {
    $data = parent::dataSet();

    // Add serialized data to each row.
    $data[0]['data'] = serialize(['key1' => 'value1', 'key2' => 'value2']);
    $data[1]['data'] = serialize(['key1' => 'different_value']);
    $data[2]['data'] = serialize(['key2' => 'value2']);
    $data[3]['data'] = serialize(['key1' => NULL]);
    $data[4]['data'] = NULL;

    return $data;
  }

  /**
   * Tests the serialized field handler with unserialized format.
   */
  public function testFieldSerializedUnserialized(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    $view->displayHandlers->get('default')->overrideOption('fields', [
      'data' => [
        'id' => 'data',
        'table' => 'views_test_data',
        'field' => 'data',
        'relationship' => 'none',
        'format' => 'unserialized',
      ],
    ]);

    $this->executeView($view);

    // The unserialized format should display print_r output.
    $expected = Html::escape(print_r(['key1' => 'value1', 'key2' => 'value2'], TRUE));
    $this->assertEquals($expected, $view->field['data']->advancedRender($view->result[0]));
  }

  /**
   * Tests the serialized field handler with serialized format.
   */
  public function testFieldSerializedSerialized(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    $view->displayHandlers->get('default')->overrideOption('fields', [
      'data' => [
        'id' => 'data',
        'table' => 'views_test_data',
        'field' => 'data',
        'relationship' => 'none',
        'format' => 'serialized',
      ],
    ]);

    $this->executeView($view);

    // The serialized format should return the raw serialized value.
    $expected = serialize(['key1' => 'value1', 'key2' => 'value2']);
    $this->assertEquals($expected, $view->field['data']->advancedRender($view->result[0]));
  }

  /**
   * Tests the serialized field handler with key format.
   */
  public function testFieldSerializedKey(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    $view->displayHandlers->get('default')->overrideOption('fields', [
      'data' => [
        'id' => 'data',
        'table' => 'views_test_data',
        'field' => 'data',
        'relationship' => 'none',
        'format' => 'key',
        'key' => 'key1',
      ],
    ]);

    $this->executeView($view);

    // Test first row - has key1 => value1.
    $this->assertEquals('value1', $view->field['data']->advancedRender($view->result[0]));

    // Test second row - has key1 => different_value.
    $this->assertEquals('different_value', $view->field['data']->advancedRender($view->result[1]));

    // Test third row - has key1 missing, should return empty string.
    $this->assertEquals('', $view->field['data']->advancedRender($view->result[2]));

    // Test fourth row - has key1 => NULL, should return empty string.
    $this->assertEquals('', $view->field['data']->advancedRender($view->result[3]));
  }

}
