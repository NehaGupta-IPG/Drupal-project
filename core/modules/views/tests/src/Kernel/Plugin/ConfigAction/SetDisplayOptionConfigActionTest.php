<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Plugin\ConfigAction;

use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;

/**
 * @covers \Drupal\views\Plugin\ConfigAction\SetDisplayOption
 *
 * @group Recipe
 * @group views
 */
class SetDisplayOptionConfigActionTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['entity_test_fields'];

  /**
   * Tests changing of view pager.
   */
  public function testSetViewsPager() : void {
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    $pager = $view->displayHandlers->get('default')->getOption('pager');
    // Check that pager type is full.
    $this->assertSame('full', $pager['type']);
    // Apply config action that set pager to mini for default display.
    $config_action_settings = [
      'option' => 'pager',
      'settings' => [
        'type' => 'mini',
        'options' => [
          'items_per_page' => 5,
        ],
      ],
    ];
    $this->container->get('plugin.manager.config_action')->applyAction('setDisplayOption', 'views.view.entity_test_fields', $config_action_settings);
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    $pager = $view->displayHandlers->get('default')->getOption('pager');
    // Check that pager type is mini.
    $this->assertSame('mini', $pager['type']);
  }

  /**
   * Tests adding a field to a default display.
   */
  public function testAddFieldToDefaultDisplay() : void {
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    $fields = $view->displayHandlers->get('default')->getOption('fields');
    // Check that field type is not part of default display.
    $this->assertArrayNotHasKey('type', $fields);
    // Apply config action that adds field type to default display.
    $config_action_settings = [
      'option' => 'fields',
      'item' => 'type',
      'settings' => [
        'id' => 'type',
        'table' => 'entity_test',
        'field' => 'type',
        'entity_type' => 'entity_test',
        'entity_field' => 'type',
        'plugin_id' => 'field',
        'exclude' => FALSE,
        'alter' => [
          'alter_text' => FALSE,
        ],
        'element_class' => '',
        'empty' => '',
        'hide_empty' => FALSE,
        'empty_zero' => FALSE,
        'hide_alter_empty' => TRUE,
      ],
    ];
    $this->container->get('plugin.manager.config_action')->applyAction('setDisplayOption', 'views.view.entity_test_fields', $config_action_settings);
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    $fields = $view->displayHandlers->get('default')->getOption('fields');
    // Check that field type now exists.
    $this->assertArrayHasKey('type', $fields);
    // Try to apply the action again without allow_update flag.
    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage('Item type already exists in default display for fields');
    $config_action_settings['allow_update'] = FALSE;
    $this->container->get('plugin.manager.config_action')->applyAction('setDisplayOption', 'views.view.entity_test_fields', $config_action_settings);
  }

  /**
   * Tests adding multiple fields to a default display.
   */
  public function testAddMultipleFieldsToDefaultDisplay() : void {
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    $fields = $view->displayHandlers->get('default')->getOption('fields');
    // Check that field type is not part of default display.
    $this->assertArrayNotHasKey('type', $fields);
    // Check that field user_id is not part of default display.
    $this->assertArrayNotHasKey('user_id', $fields);
    // Apply config action that adds field type to default display.
    $config_action_settings = [
      [
        'option' => 'fields',
        'item' => 'user_id',
        'settings' => [
          'id' => 'user_id',
          'table' => 'entity_test',
          'field' => 'user_id',
          'plugin_id' => 'field',
          'entity_type' => 'entity_test',
          'entity_field' => 'user_id',
        ],
      ],
      [
        'option' => 'fields',
        'item' => 'type',
        'item_sibling' => 'name',
        'position' => 'before',
        'settings' => [
          'id' => 'type',
          'table' => 'entity_test',
          'field' => 'type',
          'entity_type' => 'entity_test',
          'entity_field' => 'type',
          'plugin_id' => 'field',
          'exclude' => FALSE,
          'alter' => [
            'alter_text' => FALSE,
          ],
          'element_class' => '',
          'empty' => '',
          'hide_empty' => FALSE,
          'empty_zero' => FALSE,
          'hide_alter_empty' => TRUE,
        ],
      ],
    ];
    $this->container->get('plugin.manager.config_action')->applyAction('setDisplayOption', 'views.view.entity_test_fields', $config_action_settings);
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    $fields = $view->displayHandlers->get('default')->getOption('fields');
    // Check that field type now exists.
    $this->assertArrayHasKey('type', $fields);
    // Check that field user_id now exists.
    $this->assertArrayHasKey('user_id', $fields);
    // Check that type field is above name.
    $name_position = array_search('name', array_keys($fields));
    $type_position = array_search('type', array_keys($fields));
    $this->assertGreaterThan($type_position, $name_position);
  }

  /**
   * Tests adding field to a new display with override.
   */
  public function testAddFieldToOverriddenDisplay() : void {
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    $fields = $view->displayHandlers->get('default')->getOption('fields');
    // Check that field type is not part of default display.
    $this->assertArrayNotHasKey('type', $fields);
    // Create a new display.
    $config_action_settings = [
      'old_display_id' => 'default',
      'new_display_type' => 'block',
    ];
    $this->container->get('plugin.manager.config_action')->applyAction('duplicateDisplayAsType', 'views.view.entity_test_fields', $config_action_settings);
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    // Confirm that new display was created.
    $this->assertTrue($view->displayHandlers->has('block_1'));
    // Apply config action that adds field type to page_1 display only.
    $config_action_settings = [
      'display_id' => 'block_1',
      'option' => 'fields',
      'item' => 'type',
      'override' => TRUE,
      'settings' => [
        'id' => 'type',
        'table' => 'entity_test',
        'field' => 'type',
        'entity_type' => 'entity_test',
        'entity_field' => 'type',
        'plugin_id' => 'field',
        'exclude' => FALSE,
        'alter' => [
          'alter_text' => FALSE,
        ],
        'element_class' => '',
        'empty' => '',
        'hide_empty' => FALSE,
        'empty_zero' => FALSE,
        'hide_alter_empty' => TRUE,
      ],
    ];
    $this->container->get('plugin.manager.config_action')->applyAction('setDisplayOption', 'views.view.entity_test_fields', $config_action_settings);
    // Check that field was added to page_3 and not to default.
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    $fields = $view->displayHandlers->get('default')->getOption('fields');
    // Check that field type is not part of default display.
    $this->assertArrayNotHasKey('type', $fields);
    $view->setDisplay('block_1');
    $fields = $view->displayHandlers->get('block_1')->getOption('fields');
    // Check that field type is not part of default display.
    $this->assertArrayHasKey('type', $fields);
  }

}
