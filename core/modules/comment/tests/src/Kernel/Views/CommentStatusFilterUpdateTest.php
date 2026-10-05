<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Kernel\Views;

use Drupal\comment\CommentingStatus;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the update path for numeric filters on comment field status columns.
 *
 * @see comment_post_update_views_status_filter()
 * @see \Drupal\comment\Hook\CommentViewsHooks::viewPresave()
 */
#[Group('comment')]
#[RunTestsInSeparateProcesses]
class CommentStatusFilterUpdateTest extends KernelTestBase {

  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'field',
    'filter',
    'node',
    'system',
    'text',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['filter', 'node']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    FieldStorageConfig::create([
      'type' => 'text_long',
      'entity_type' => 'comment',
      'field_name' => 'comment_body',
    ])->save();
    $this->addDefaultCommentField('node', 'page', 'comment');
    $this->addDefaultCommentField('node', 'page', 'comment_other');

    $nodes = [
      'open-hidden' => [CommentingStatus::Open, CommentingStatus::Hidden],
      'closed-open' => [CommentingStatus::Closed, CommentingStatus::Open],
      'hidden-closed' => [CommentingStatus::Hidden, CommentingStatus::Closed],
    ];
    foreach ($nodes as $title => [$comment, $other]) {
      Node::create([
        'type' => 'page',
        'title' => $title,
        'comment' => ['status' => $comment->value],
        'comment_other' => ['status' => $other->value],
      ])->save();
    }
  }

  /**
   * Builds a numeric filter as saved before the comment status filter existed.
   */
  protected function legacyFilter(string $field_name, string $operator, array $value = []): array {
    return [
      'id' => $field_name . '_status',
      'table' => 'node__' . $field_name,
      'field' => $field_name . '_status',
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => '',
      'plugin_id' => 'numeric',
      'operator' => $operator,
      'value' => $value + ['min' => '', 'max' => '', 'value' => ''],
      'group' => 1,
      'exposed' => FALSE,
      'expose' => [
        'operator_id' => '',
        'label' => '',
        'description' => '',
        'use_operator' => FALSE,
        'operator' => '',
        'operator_limit_selection' => FALSE,
        'operator_list' => [],
        'identifier' => '',
        'required' => FALSE,
        'remember' => FALSE,
        'multiple' => FALSE,
        'remember_roles' => ['authenticated' => 'authenticated'],
        'min_placeholder' => '',
        'max_placeholder' => '',
        'placeholder' => '',
        'min_label' => 'Min',
        'max_label' => 'Max',
      ],
      'is_grouped' => FALSE,
      'group_info' => [],
    ];
  }

  /**
   * Builds an unsaved view with the given filters per display.
   *
   * @param string $id
   *   The view ID.
   * @param array $filters
   *   Filters keyed by display ID. The default display is always created; an
   *   "embed" display is created with overridden filters when given.
   */
  protected function buildView(string $id, array $filters): View {
    $display = [
      'default' => [
        'id' => 'default',
        'display_plugin' => 'default',
        'position' => 0,
        'display_options' => [
          'fields' => [
            'title' => [
              'id' => 'title',
              'table' => 'node_field_data',
              'field' => 'title',
              'entity_type' => 'node',
              'entity_field' => 'title',
              'plugin_id' => 'field',
            ],
          ],
          'filters' => $filters['default'] ?? [],
          'pager' => ['type' => 'none'],
          'style' => ['type' => 'default'],
          'row' => ['type' => 'fields'],
        ],
      ],
    ];
    if (isset($filters['embed'])) {
      $display['embed'] = [
        'id' => 'embed',
        'display_plugin' => 'embed',
        'position' => 1,
        'display_options' => [
          'filters' => $filters['embed'],
          'defaults' => ['filters' => FALSE, 'filter_groups' => FALSE],
        ],
      ];
    }
    return View::create([
      'id' => $id,
      'label' => $id,
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
      'display' => $display,
    ]);
  }

  /**
   * Stores a view with the given filters as it was saved before the update.
   *
   * The configuration is written directly, so that hook_view_presave() does
   * not convert the filters.
   *
   * @param string $id
   *   The view ID.
   * @param array $filters
   *   Filters keyed by display ID.
   *
   * @see self::buildView()
   */
  protected function createView(string $id, array $filters): void {
    $this->config('views.view.' . $id)
      ->setData($this->buildView($id, $filters)->toArray())
      ->save();
  }

  /**
   * Runs the post-update function until it is finished.
   */
  protected function runUpdate(): ?\Stringable {
    $function = 'comment_post_update_views_status_filter';
    $this->assertContains($function, $this->container->get('update.post_update_registry')->getPendingUpdateFunctions());
    $sandbox = [];
    do {
      $message = $function($sandbox);
    } while ($sandbox['#finished'] < 1);
    return $message;
  }

  /**
   * Executes a view display and returns the sorted result titles.
   *
   * @return string[]
   *   The titles of the result rows.
   */
  protected function executeView(string $id, string $display_id = 'default', array $input = []): array {
    $view = Views::getView($id);
    $view->setDisplay($display_id);
    $view->setExposedInput($input);
    $view->execute();
    $titles = array_map(fn($row) => $row->_entity->label(), $view->result);
    sort($titles);
    return $titles;
  }

  /**
   * Returns a saved filter of a view display.
   */
  protected function getFilter(string $id, string $filter_id, string $display_id = 'default'): array {
    return View::load($id)->getDisplay($display_id)['display_options']['filters'][$filter_id];
  }

  /**
   * Tests the conversion of each numeric operator.
   */
  #[DataProvider('providerOperators')]
  public function testOperatorConversion(string $operator, array $value, string $expected_operator, array $expected_value, array $expected_titles): void {
    $this->createView('legacy', ['default' => ['comment_status' => $this->legacyFilter('comment', $operator, $value)]]);
    $this->assertNull($this->runUpdate());

    $filter = $this->getFilter('legacy', 'comment_status');
    $this->assertSame('node_comment', $filter['plugin_id']);
    $this->assertSame($expected_operator, $filter['operator']);
    $this->assertSame($expected_value, $filter['value']);
    $this->assertArrayNotHasKey('min_placeholder', $filter['expose']);
    $this->assertArrayNotHasKey('min_label', $filter['expose']);
    $this->assertFalse($filter['expose']['reduce']);
    $this->assertSame($expected_titles, $this->executeView('legacy'));
  }

  /**
   * Data provider for testOperatorConversion().
   */
  public static function providerOperators(): array {
    return [
      'equal' => ['=', ['value' => '0'], 'in', [0 => '0'], ['hidden-closed']],
      'not equal' => ['!=', ['value' => '2'], 'not in', [2 => '2'], ['closed-open', 'hidden-closed']],
      'less than' => ['<', ['value' => '2'], 'in', [1 => '1', 0 => '0'], ['closed-open', 'hidden-closed']],
      'less than or equal' => ['<=', ['value' => '0'], 'in', [0 => '0'], ['hidden-closed']],
      'greater than' => ['>', ['value' => '0'], 'in', [2 => '2', 1 => '1'], ['closed-open', 'open-hidden']],
      'greater than or equal' => ['>=', ['value' => '2'], 'in', [2 => '2'], ['open-hidden']],
      'between' => [
        'between', ['min' => '0', 'max' => '1'], 'in', [1 => '1', 0 => '0'], ['closed-open', 'hidden-closed'],
      ],
      'between min only' => ['between', ['min' => '1'], 'in', [2 => '2', 1 => '1'], ['closed-open', 'open-hidden']],
      'not between' => [
        'not between', ['min' => '1', 'max' => '1'], 'in', [2 => '2', 0 => '0'], ['hidden-closed', 'open-hidden'],
      ],
      'empty' => ['empty', [], 'empty', [], []],
      'not empty' => ['not empty', [], 'not empty', [], ['closed-open', 'hidden-closed', 'open-hidden']],
      'no value' => ['=', [], 'in', [], ['closed-open', 'hidden-closed', 'open-hidden']],
    ];
  }

  /**
   * Tests that every display and only comment status filters are converted.
   */
  public function testDisplaysAndUnrelatedFilters(): void {
    $nid = [
      'id' => 'nid',
      'table' => 'node_field_data',
      'field' => 'nid',
      'plugin_id' => 'numeric',
      'operator' => '>',
      'value' => ['min' => '', 'max' => '', 'value' => '0'],
    ];
    // A numeric filter on another column of the comment field table.
    $timestamp = [
      'id' => 'comment_last_comment_timestamp',
      'table' => 'node__comment',
      'field' => 'comment_last_comment_timestamp',
      'plugin_id' => 'numeric',
      'operator' => '>=',
      'value' => ['min' => '', 'max' => '', 'value' => '0'],
    ];
    $this->createView('legacy', [
      'default' => [
        'nid' => $nid,
        'comment_status' => $this->legacyFilter('comment', '=', ['value' => '1']),
      ],
      'embed' => [
        'nid' => $nid,
        'comment_last_comment_timestamp' => $timestamp,
        'comment_other_status' => $this->legacyFilter('comment_other', '!=', ['value' => '0']),
      ],
    ]);
    // Before the update, the numeric operators add no query condition.
    $this->assertSame(['closed-open', 'hidden-closed', 'open-hidden'], $this->executeView('legacy'));
    $this->assertNull($this->runUpdate());

    $filter = $this->getFilter('legacy', 'comment_status');
    $this->assertSame('node_comment', $filter['plugin_id']);
    $this->assertSame('in', $filter['operator']);
    $this->assertSame([1 => '1'], $filter['value']);
    $filter = $this->getFilter('legacy', 'comment_other_status', 'embed');
    $this->assertSame('node_comment', $filter['plugin_id']);
    $this->assertSame('not in', $filter['operator']);
    $this->assertSame([0 => '0'], $filter['value']);
    foreach (['default', 'embed'] as $display_id) {
      $this->assertSame($nid['value'], $this->getFilter('legacy', 'nid', $display_id)['value']);
      $this->assertSame('numeric', $this->getFilter('legacy', 'nid', $display_id)['plugin_id']);
      $this->assertSame('>', $this->getFilter('legacy', 'nid', $display_id)['operator']);
    }
    $filter = $this->getFilter('legacy', 'comment_last_comment_timestamp', 'embed');
    $this->assertSame('numeric', $filter['plugin_id']);
    $this->assertSame('>=', $filter['operator']);

    $this->assertSame(['closed-open'], $this->executeView('legacy'));
    $this->assertSame(['closed-open', 'hidden-closed'], $this->executeView('legacy', 'embed'));
  }

  /**
   * Tests the conversion of exposed and grouped filters.
   */
  public function testExposedAndGroupedFilters(): void {
    $exposed = $this->legacyFilter('comment', '=');
    $exposed['exposed'] = TRUE;
    $exposed['expose']['identifier'] = 'comment_status';
    $exposed['expose']['operator_id'] = 'comment_status_op';
    $exposed['expose']['operator_list'] = ['=' => '=', '>' => '>', 'regular_expression' => 'regular_expression'];

    $grouped = $this->legacyFilter('comment_other', '=');
    $grouped['exposed'] = TRUE;
    $grouped['is_grouped'] = TRUE;
    $grouped['expose']['operator_id'] = 'comment_other_status_op';
    $grouped['expose']['operator'] = 'comment_other_status_op';
    $grouped['expose']['identifier'] = 'comment_other_status';
    $grouped['group_info'] = [
      'label' => 'Other',
      'description' => '',
      'identifier' => 'comment_other_status',
      'optional' => TRUE,
      'widget' => 'select',
      'multiple' => FALSE,
      'remember' => FALSE,
      'default_group' => 'All',
      'default_group_multiple' => [],
      'group_items' => [
        1 => ['title' => 'Open', 'operator' => '=', 'value' => ['min' => '', 'max' => '', 'value' => '2']],
        2 => ['title' => 'Visible', 'operator' => '>', 'value' => ['min' => '', 'max' => '', 'value' => '0']],
        3 => ['title' => '', 'operator' => '=', 'value' => ['min' => '', 'max' => '', 'value' => '']],
      ],
    ];
    $this->createView('legacy', ['default' => ['comment_status' => $exposed, 'comment_other_status' => $grouped]]);
    $this->assertNull($this->runUpdate());

    $filter = $this->getFilter('legacy', 'comment_status');
    $this->assertSame('in', $filter['operator']);
    $this->assertSame([], $filter['value']);
    $this->assertSame(['in' => 'in'], $filter['expose']['operator_list']);

    $filter = $this->getFilter('legacy', 'comment_other_status');
    $this->assertSame('node_comment', $filter['plugin_id']);
    $this->assertSame([
      1 => ['title' => 'Open', 'operator' => 'in', 'value' => [2 => '2']],
      2 => ['title' => 'Visible', 'operator' => 'in', 'value' => [2 => '2', 1 => '1']],
      3 => ['title' => '', 'operator' => 'in', 'value' => []],
    ], $filter['group_info']['group_items']);

    $this->assertSame(['closed-open', 'hidden-closed', 'open-hidden'], $this->executeView('legacy'));
    $this->assertSame(['open-hidden'], $this->executeView('legacy', 'default', ['comment_status' => '2']));
    $this->assertSame(['closed-open'], $this->executeView('legacy', 'default', ['comment_other_status' => '1']));
    $this->assertSame(['closed-open', 'hidden-closed'], $this->executeView('legacy', 'default', ['comment_other_status' => '2']));
  }

  /**
   * Tests that filters without a status equivalent are left unchanged.
   */
  public function testUnconvertedFilters(): void {
    $filters = [
      'regular_expression' => $this->legacyFilter('comment', 'regular_expression', ['value' => '^2$']),
      'unknown_status' => $this->legacyFilter('comment', '=', ['value' => '7']),
      'no_status' => $this->legacyFilter('comment', '<', ['value' => '0']),
    ];
    $filters['converted'] = $this->legacyFilter('comment_other', '=', ['value' => '2']);
    foreach ($filters as $filter_id => &$filter) {
      $filter['id'] = $filter_id;
    }
    unset($filter);
    $this->createView('legacy', ['default' => $filters]);
    $saved = View::load('legacy')->getDisplay('default')['display_options']['filters'];
    $message = $this->runUpdate();

    $this->assertNotNull($message);
    $this->assertStringContainsString('legacy:default:regular_expression, legacy:default:unknown_status, legacy:default:no_status', (string) $message);
    foreach (['regular_expression', 'unknown_status', 'no_status'] as $filter_id) {
      $this->assertSame($saved[$filter_id], $this->getFilter('legacy', $filter_id));
    }
    $this->assertSame('node_comment', $this->getFilter('legacy', 'converted')['plugin_id']);
  }

  /**
   * Tests that saving a view converts numeric comment status filters.
   */
  #[IgnoreDeprecations]
  public function testPresaveConversion(): void {
    $this->expectUserDeprecationMessage('The update to convert numeric filters on comment field status columns for view "legacy" is deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. Profile, module and theme provided configuration should be updated. See https://www.drupal.org/project/drupal/issues/3031364');
    $nid = [
      'id' => 'nid',
      'table' => 'node_field_data',
      'field' => 'nid',
      'plugin_id' => 'numeric',
      'operator' => '>',
      'value' => ['min' => '', 'max' => '', 'value' => '0'],
    ];
    $timestamp = [
      'id' => 'comment_last_comment_timestamp',
      'table' => 'node__comment',
      'field' => 'comment_last_comment_timestamp',
      'plugin_id' => 'numeric',
      'operator' => '>=',
      'value' => ['min' => '', 'max' => '', 'value' => '0'],
    ];
    $this->buildView('legacy', [
      'default' => [
        'nid' => $nid,
        'comment_status' => $this->legacyFilter('comment', 'between', ['min' => '1', 'max' => '2']),
        'comment_other_status' => $this->legacyFilter('comment_other', '!=', ['value' => '0']),
      ],
      'embed' => [
        'comment_last_comment_timestamp' => $timestamp,
        'comment_status' => $this->legacyFilter('comment', '=', ['value' => '0']),
      ],
    ])->save();

    $filter = $this->getFilter('legacy', 'comment_status');
    $this->assertSame('node_comment', $filter['plugin_id']);
    $this->assertSame('in', $filter['operator']);
    $this->assertSame([2 => '2', 1 => '1'], $filter['value']);
    $filter = $this->getFilter('legacy', 'comment_other_status');
    $this->assertSame('node_comment', $filter['plugin_id']);
    $this->assertSame('not in', $filter['operator']);
    $this->assertSame([0 => '0'], $filter['value']);
    $filter = $this->getFilter('legacy', 'comment_status', 'embed');
    $this->assertSame('node_comment', $filter['plugin_id']);
    $this->assertSame('in', $filter['operator']);
    $this->assertSame([0 => '0'], $filter['value']);
    $this->assertSame($nid, $this->getFilter('legacy', 'nid'));
    $this->assertSame($timestamp, $this->getFilter('legacy', 'comment_last_comment_timestamp', 'embed'));

    $this->assertSame(['closed-open'], $this->executeView('legacy'));
    $this->assertSame(['hidden-closed'], $this->executeView('legacy', 'embed'));
  }

  /**
   * Tests that saving a view leaves other filters unchanged.
   */
  public function testPresaveUnchanged(): void {
    $converted = $this->legacyFilter('comment', '=');
    unset($converted['expose']['min_placeholder'], $converted['expose']['max_placeholder'], $converted['expose']['placeholder'], $converted['expose']['min_label'], $converted['expose']['max_label']);
    $converted['expose']['reduce'] = FALSE;
    $converted['plugin_id'] = 'node_comment';
    $converted['operator'] = 'in';
    $converted['value'] = [2 => '2'];
    $filters = [
      'nid' => [
        'id' => 'nid',
        'table' => 'node_field_data',
        'field' => 'nid',
        'plugin_id' => 'numeric',
        'operator' => '>',
        'value' => ['min' => '', 'max' => '', 'value' => '0'],
      ],
      'comment_status' => $converted,
      'comment_other_status' => $this->legacyFilter('comment_other', 'regular_expression', [
        'min' => '',
        'max' => '',
        'value' => '^0$',
      ]),
    ];
    $this->buildView('legacy', ['default' => $filters])->save();

    $this->assertSame($filters, View::load('legacy')->getDisplay('default')['display_options']['filters']);
    $this->assertSame(['open-hidden'], $this->executeView('legacy'));
  }

}
