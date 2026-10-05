<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Kernel\Views;

use Drupal\comment\CommentingStatus;
use Drupal\comment\Plugin\views\field\CommentStatus;
use Drupal\comment\Plugin\views\filter\NodeComment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\StringTranslation\Translator\StaticTranslation;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\views\Entity\View;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests filtering a view on the status of a comment field.
 */
#[Group('comment')]
#[RunTestsInSeparateProcesses]
class CommentStatusFilterTest extends KernelTestBase {

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
  }

  /**
   * Creates a node with the given statuses for the two comment fields.
   */
  protected function createNodeWithStatuses(string $title, CommentingStatus $comment, CommentingStatus $other): int {
    $node = Node::create([
      'type' => 'page',
      'title' => $title,
      'comment' => ['status' => $comment->value],
      'comment_other' => ['status' => $other->value],
    ]);
    $node->save();
    return (int) $node->id();
  }

  /**
   * Builds and executes a view filtering on a comment field's status.
   *
   * @return string[]
   *   The titles of the result rows, sorted.
   */
  protected function executeFilteredView(string $field_name, array $statuses, ?ViewExecutable &$view = NULL): array {
    $view_entity = View::create([
      'id' => 'test_comment_status_' . $field_name . '_' . implode('_', $statuses),
      'label' => 'Test',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
      'display' => [
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
            'filters' => [
              $field_name . '_status' => [
                'id' => $field_name . '_status',
                'table' => 'node__' . $field_name,
                'field' => $field_name . '_status',
                'plugin_id' => 'node_comment',
                'operator' => 'in',
                'value' => array_combine($statuses, $statuses),
              ],
            ],
            'pager' => ['type' => 'none'],
            'style' => ['type' => 'default'],
            'row' => ['type' => 'fields'],
          ],
        ],
      ],
    ]);
    $view_entity->save();
    $view = Views::getView($view_entity->id());
    $view->setDisplay('default');
    $view->execute();
    $titles = array_map(fn($row) => $row->_entity->label(), $view->result);
    sort($titles);
    return $titles;
  }

  /**
   * Tests the comment status filter of each comment field.
   */
  public function testCommentStatusFilter(): void {
    $this->createNodeWithStatuses('open-hidden', CommentingStatus::Open, CommentingStatus::Hidden);
    $this->createNodeWithStatuses('closed-open', CommentingStatus::Closed, CommentingStatus::Open);
    $this->createNodeWithStatuses('hidden-closed', CommentingStatus::Hidden, CommentingStatus::Closed);

    $view = NULL;
    $result = $this->executeFilteredView('comment', [CommentingStatus::Hidden->value], $view);
    $this->assertSame(['hidden-closed'], $result);
    $this->assertInstanceOf(NodeComment::class, $view->filter['comment_status']);
    $this->assertStatusCondition($view, 'node__comment.comment_status');

    $result = $this->executeFilteredView('comment', [CommentingStatus::Open->value, CommentingStatus::Closed->value]);
    $this->assertSame(['closed-open', 'open-hidden'], $result);

    // The second comment field exposes its own working filter.
    $result = $this->executeFilteredView('comment_other', [CommentingStatus::Closed->value], $view);
    $this->assertSame(['hidden-closed'], $result);
    $this->assertInstanceOf(NodeComment::class, $view->filter['comment_other_status']);
    $this->assertStatusCondition($view, 'node__comment_other.comment_other_status');
  }

  /**
   * Tests that the comment status filter offers the status options.
   */
  public function testCommentStatusFilterOptions(): void {
    $view = NULL;
    $this->executeFilteredView('comment', [CommentingStatus::Open->value], $view);
    $options = $view->filter['comment_status']->getValueOptions();
    $this->assertSame([
      CommentingStatus::Open->value,
      CommentingStatus::Closed->value,
      CommentingStatus::Hidden->value,
    ], array_keys($options));
    $this->assertSame([
      CommentingStatus::Open->value => 'Open',
      CommentingStatus::Closed->value => 'Closed',
      CommentingStatus::Hidden->value => 'Hidden',
    ], array_map('strval', $options));
  }

  /**
   * Tests that the comment status field displays the status labels.
   */
  public function testCommentStatusField(): void {
    $this->createNodeWithStatuses('open-hidden', CommentingStatus::Open, CommentingStatus::Hidden);
    $this->createNodeWithStatuses('closed-open', CommentingStatus::Closed, CommentingStatus::Open);
    $this->createNodeWithStatuses('hidden-closed', CommentingStatus::Hidden, CommentingStatus::Closed);

    $expected = [
      'comment' => ['open-hidden' => 'Open', 'closed-open' => 'Closed', 'hidden-closed' => 'Hidden'],
      'comment_other' => ['open-hidden' => 'Hidden', 'closed-open' => 'Open', 'hidden-closed' => 'Closed'],
    ];
    foreach ($expected as $field_name => $labels) {
      $view = $this->executeStatusFieldView($field_name);
      $this->assertInstanceOf(CommentStatus::class, $view->field[$field_name . '_status']);
      $actual = [];
      foreach ($view->result as $row) {
        $actual[$row->_entity->label()] = (string) $view->field[$field_name . '_status']->advancedRender($row);
      }
      $this->assertSame($labels, $actual, $field_name);
    }
  }

  /**
   * Tests that the comment status field translates the status labels.
   */
  public function testCommentStatusFieldTranslated(): void {
    $translation = $this->container->get('string_translation');
    $translation->addTranslator(new StaticTranslation([
      'fr' => ['' => ['Open' => 'Ouvert', 'Closed' => 'Fermé', 'Hidden' => 'Masqué']],
    ]));
    $translation->setDefaultLangcode('fr');

    $this->createNodeWithStatuses('open-hidden', CommentingStatus::Open, CommentingStatus::Hidden);
    $this->createNodeWithStatuses('closed-open', CommentingStatus::Closed, CommentingStatus::Open);
    $this->createNodeWithStatuses('hidden-closed', CommentingStatus::Hidden, CommentingStatus::Closed);

    $view = $this->executeStatusFieldView('comment');
    $actual = [];
    foreach ($view->result as $row) {
      $actual[$row->_entity->label()] = (string) $view->field['comment_status']->advancedRender($row);
    }
    $this->assertSame(['open-hidden' => 'Ouvert', 'closed-open' => 'Fermé', 'hidden-closed' => 'Masqué'], $actual);
  }

  /**
   * Tests that an unexpected stored status value renders without error.
   */
  public function testCommentStatusFieldUnexpectedValue(): void {
    $node = Node::create([
      'type' => 'page',
      'title' => 'odd',
      'comment' => ['status' => 7],
    ]);
    $node->save();
    $view = $this->executeStatusFieldView('comment');
    $this->assertCount(1, $view->result);
    $output = (string) $view->field['comment_status']->advancedRender($view->result[0]);
    $this->assertSame('7', $output);
  }

  /**
   * Builds and executes a view displaying a comment field's status.
   */
  protected function executeStatusFieldView(string $field_name): ViewExecutable {
    $view_entity = View::create([
      'id' => 'test_comment_status_field_' . $field_name,
      'label' => 'Test',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
      'display' => [
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
              $field_name . '_status' => [
                'id' => $field_name . '_status',
                'table' => 'node__' . $field_name,
                'field' => $field_name . '_status',
                'plugin_id' => 'comment_status',
              ],
            ],
            'pager' => ['type' => 'none'],
            'style' => ['type' => 'default'],
            'row' => ['type' => 'fields'],
          ],
        ],
      ],
    ]);
    $view_entity->save();
    $view = Views::getView($view_entity->id());
    $view->setDisplay('default');
    $view->execute();
    return $view;
  }

  /**
   * Asserts the query has an IN condition on the given field.
   */
  protected function assertStatusCondition(ViewExecutable $view, string $field): void {
    $conditions = [];
    foreach ($view->query->where as $group) {
      foreach ($group['conditions'] as $condition) {
        if ($condition['field'] === $field) {
          $conditions[] = strtoupper($condition['operator']);
        }
      }
    }
    $this->assertSame(['IN'], $conditions);
  }

  /**
   * Tests that only the status column of comment fields uses node_comment.
   */
  public function testViewsDataFilterIds(): void {
    $views_data = $this->container->get('views.views_data');
    $tables = [
      'node__comment' => 'comment',
      'node_revision__comment' => 'comment',
      'node__comment_other' => 'comment_other',
    ];
    foreach ($tables as $table => $field_name) {
      $data = $views_data->get($table);
      $this->assertSame('node_comment', $data[$field_name . '_status']['filter']['id'], $table);
      // No other filter in the table uses the comment status handler.
      foreach ($data as $key => $definition) {
        if ($key !== $field_name . '_status' && isset($definition['filter']['id'])) {
          $this->assertNotSame('node_comment', $definition['filter']['id'], "$table.$key");
        }
      }
    }
  }

}
