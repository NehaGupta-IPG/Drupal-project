<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Plugin\ConfigAction;

use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Plugin\ConfigAction\SetDisplayOption;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\String\UnicodeString;

/**
 * Tests the config actions that set Views display options.
 */
#[CoversClass(SetDisplayOption::class)]
#[Group('Recipe')]
#[Group('views')]
#[RunTestsInSeparateProcesses]
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
        'position' => ['name', 'before'],
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

  /**
   * Tests duplicating multiple displays with the pluralized action.
   */
  public function testDuplicateDisplaysAsType() : void {
    $config_action_settings = [
      ['old_display_id' => 'default', 'new_display_type' => 'block'],
      ['old_display_id' => 'default', 'new_display_type' => 'attachment'],
    ];
    $this->container->get('plugin.manager.config_action')->applyAction('duplicateDisplaysAsType', 'views.view.entity_test_fields', $config_action_settings);
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    $this->assertTrue($view->displayHandlers->has('block_1'));
    $this->assertTrue($view->displayHandlers->has('attachment_1'));
  }

  /**
   * Tests the derived single value action for the pager.
   */
  public function testSetPagerInDisplay() : void {
    $config_action_settings = [
      'settings' => [
        'type' => 'mini',
        'options' => [
          'items_per_page' => 5,
        ],
      ],
    ];
    $this->container->get('plugin.manager.config_action')->applyAction('setPagerInDisplay', 'views.view.entity_test_fields', $config_action_settings);
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    $pager = $view->displayHandlers->get('default')->getOption('pager');
    $this->assertSame('mini', $pager['type']);
    $this->assertSame(5, $pager['options']['items_per_page']);
  }

  /**
   * Tests that single value actions accept a FALSE value.
   */
  public function testSetUseAjaxInDisplay() : void {
    $manager = $this->container->get('plugin.manager.config_action');
    $manager->applyAction('setUseAjaxInDisplay', 'views.view.entity_test_fields', ['settings' => TRUE]);
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    $this->assertTrue($view->displayHandlers->get('default')->getOption('use_ajax'));
    $manager->applyAction('setUseAjaxInDisplay', 'views.view.entity_test_fields', ['settings' => FALSE]);
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    $this->assertFalse($view->displayHandlers->get('default')->getOption('use_ajax'));
  }

  /**
   * Tests the derived field action with a typed name and sibling position.
   */
  public function testSetFieldInDisplay() : void {
    $config_action_settings = [
      'field_name' => 'type',
      'position' => ['name', 'before'],
      'settings' => [
        'id' => 'type',
        'table' => 'entity_test',
        'field' => 'type',
        'entity_type' => 'entity_test',
        'entity_field' => 'type',
        'plugin_id' => 'field',
      ],
    ];
    $this->container->get('plugin.manager.config_action')->applyAction('setFieldInDisplay', 'views.view.entity_test_fields', $config_action_settings);
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    $fields = array_keys($view->displayHandlers->get('default')->getOption('fields'));
    $this->assertGreaterThan(array_search('type', $fields), array_search('name', $fields));
  }

  /**
   * Tests that the derived field action requires the typed name.
   */
  public function testSetFieldInDisplayRequiresFieldName() : void {
    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage('No field_name provided');
    $this->container->get('plugin.manager.config_action')->applyAction('setFieldInDisplay', 'views.view.entity_test_fields', ['settings' => ['id' => 'type']]);
  }

  /**
   * Tests every derived single value action.
   *
   * @param string $action
   *   The config action ID.
   * @param string $option
   *   The display option the action is expected to set.
   * @param mixed $settings
   *   The settings to apply.
   */
  #[DataProvider('singleValueActionProvider')]
  public function testSingleValueActions(string $action, string $option, mixed $settings) : void {
    $this->container->get('plugin.manager.config_action')->applyAction($action, 'views.view.entity_test_fields', ['settings' => $settings]);
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    $this->assertSame($settings, $view->displayHandlers->get('default')->getOption($option));
  }

  /**
   * Data provider for ::testSingleValueActions().
   */
  public static function singleValueActionProvider() : array {
    $plugin = ['type' => 'none', 'options' => []];
    $cases = [
      'access' => $plugin,
      'cache' => $plugin,
      'css_class' => 'my-class',
      'exposed_form' => ['type' => 'basic', 'options' => []],
      'filter_groups' => [
        'operator' => 'OR',
        'groups' => [1 => 'AND', 2 => 'OR'],
      ],
      'group_by' => TRUE,
      'hide_attachment_summary' => TRUE,
      'link_display' => 'custom_url',
      'pager' => ['type' => 'mini', 'options' => ['items_per_page' => 7]],
      'query' => [
        'type' => 'views_query',
        'options' => ['query_comment' => 'Comment'],
      ],
      'row' => ['type' => 'fields', 'options' => []],
      'show_admin_links' => FALSE,
      'style' => ['type' => 'default', 'options' => []],
      'title' => 'A new title',
      'use_ajax' => TRUE,
      'use_more_always' => FALSE,
      'use_more_text' => 'Read on',
    ];
    $data = [];
    foreach ($cases as $option => $settings) {
      $action = 'set' . (new UnicodeString($option))->camel()->title() . 'InDisplay';
      $data[$option] = [$action, $option, $settings];
    }
    return $data;
  }

  /**
   * Tests the derived use_more action, which needs a link target to validate.
   */
  public function testUseMoreInDisplay() : void {
    $manager = $this->container->get('plugin.manager.config_action');
    $manager->applyAction('setLinkDisplayInDisplay', 'views.view.entity_test_fields', ['settings' => 'custom_url']);
    $manager->applyAction('setDisplayOption', 'views.view.entity_test_fields', [
      'option' => 'link_url',
      'settings' => '/some/path',
    ]);
    $manager->applyAction('setUseMoreInDisplay', 'views.view.entity_test_fields', ['settings' => TRUE]);
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    $this->assertTrue($view->displayHandlers->get('default')->getOption('use_more'));
  }

  /**
   * Tests every derived handler action.
   *
   * @param string $action
   *   The config action ID.
   * @param string $name_key
   *   The payload key holding the item name.
   * @param string $option
   *   The display option the action is expected to change.
   * @param array $settings
   *   The handler settings to apply.
   */
  #[DataProvider('handlerActionProvider')]
  public function testHandlerActions(string $action, string $name_key, string $option, array $settings) : void {
    $payload = [
      $name_key => 'config_action_item',
      'settings' => $settings,
    ];
    $this->container->get('plugin.manager.config_action')->applyAction($action, 'views.view.entity_test_fields', $payload);
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    $items = $view->displayHandlers->get('default')->getOption($option);
    $this->assertArrayHasKey('config_action_item', $items);
    $this->assertEquals($settings, $items['config_action_item']);

    // Applying the action again must fail when updates are not allowed.
    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage('already exists');
    $this->container->get('plugin.manager.config_action')->applyAction($action, 'views.view.entity_test_fields', $payload + ['allow_update' => FALSE]);
  }

  /**
   * Data provider for ::testHandlerActions().
   */
  public static function handlerActionProvider() : array {
    $id = 'config_action_item';
    $area = [
      'id' => $id,
      'table' => 'views',
      'field' => 'result',
      'plugin_id' => 'result',
      'content' => '@total results',
    ];
    $entity_test = ['table' => 'entity_test', 'entity_type' => 'entity_test'];
    $handlers = [
      'field' => ['fields', [
        'field' => 'type',
        'entity_field' => 'type',
        'plugin_id' => 'field',
      ],
      ],
      'filter' => ['filters', [
        'field' => 'name',
        'entity_field' => 'name',
        'plugin_id' => 'string',
        'operator' => 'contains',
        'value' => 'foo',
      ],
      ],
      'sort' => ['sorts', [
        'field' => 'id',
        'entity_field' => 'id',
        'plugin_id' => 'standard',
        'order' => 'DESC',
      ],
      ],
      'argument' => ['arguments', [
        'field' => 'id',
        'entity_field' => 'id',
        'plugin_id' => 'numeric',
      ],
      ],
      'relationship' => ['relationships', [
        'field' => 'user_id',
        'entity_field' => 'user_id',
        'plugin_id' => 'standard',
        'required' => FALSE,
      ],
      ],
    ];
    $data = [];
    foreach ($handlers as $type => [$option, $settings]) {
      $data[$type] = [
        'set' . ucfirst($type) . 'InDisplay',
        $type . '_name',
        $option,
        ['id' => $id] + $entity_test + $settings,
      ];
    }
    foreach (['header', 'footer', 'empty'] as $type) {
      $data[$type] = [
        'set' . ucfirst($type) . 'InDisplay',
        $type . '_name',
        $type,
        $area,
      ];
    }
    return $data;
  }

  /**
   * Tests placing an item after a sibling, which is the default direction.
   */
  public function testSetFieldInDisplayAfterSibling() : void {
    $config_action_settings = [
      'field_name' => 'type',
      'position' => ['id'],
      'settings' => [
        'id' => 'type',
        'table' => 'entity_test',
        'field' => 'type',
        'entity_type' => 'entity_test',
        'entity_field' => 'type',
        'plugin_id' => 'field',
      ],
    ];
    $this->container->get('plugin.manager.config_action')->applyAction('setFieldInDisplay', 'views.view.entity_test_fields', $config_action_settings);
    $view = Views::getView('entity_test_fields');
    $view->setDisplay();
    $fields = array_keys($view->displayHandlers->get('default')->getOption('fields'));
    $this->assertSame(array_search('id', $fields) + 1, array_search('type', $fields));
  }

  /**
   * Tests that invalid positions are rejected.
   */
  #[DataProvider('invalidPositionProvider')]
  public function testInvalidPosition(mixed $position, string $message) : void {
    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage($message);
    $config_action_settings = [
      'field_name' => 'type',
      'position' => $position,
      'settings' => ['id' => 'type'],
    ];
    $this->container->get('plugin.manager.config_action')->applyAction('setFieldInDisplay', 'views.view.entity_test_fields', $config_action_settings);
  }

  /**
   * Data provider for ::testInvalidPosition().
   */
  public static function invalidPositionProvider() : array {
    return [
      'string' => ['name', 'must be a list'],
      'empty list' => [[], 'must be a list'],
      'too many items' => [['name', 'before', 'x'], 'must be a list'],
      'invalid direction' => [['name', 'next to'], "Position 'next to' is not valid"],
      'unknown sibling' => [['missing', 'before'], 'Item sibling missing does not exist'],
    ];
  }

}
