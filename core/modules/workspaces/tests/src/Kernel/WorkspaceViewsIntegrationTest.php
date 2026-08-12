<?php

declare(strict_types=1);

namespace Drupal\Tests\workspaces\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\node\Traits\PromotedContentViewTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Entity\View;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Drupal\views_ui\ViewUI;
use Drupal\workspaces\Entity\Workspace;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the views integration for workspaces.
 */
#[Group('views')]
#[Group('workspaces')]
#[RunTestsInSeparateProcesses]
class WorkspaceViewsIntegrationTest extends ViewsKernelTestBase {

  use ContentTypeCreationTrait;
  use EntityReferenceFieldCreationTrait;
  use NodeCreationTrait;
  use PromotedContentViewTestTrait;
  use UserCreationTrait;
  use WorkspaceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'entity_test',
    'field',
    'filter',
    'node',
    'language',
    'text',
    'views_ui',
    'workspaces',
  ];

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_distinct_click_sorting'];

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Creation timestamp that should be incremented for each new entity.
   */
  protected int $createdTimestamp = 0;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp(FALSE);

    $this->entityTypeManager = \Drupal::entityTypeManager();

    $this->installEntitySchema('entity_test_mulrevpub');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('workspace');

    $this->installConfig(['filter', 'node', 'system', 'language', 'content_translation']);

    $this->installSchema('node', ['node_access']);
    $this->installSchema('workspaces', ['workspace_association', 'workspace_association_revision']);

    $language = ConfigurableLanguage::createFromLangcode('ro');
    $language->save();

    $this->createContentType(['type' => 'page']);
    $this->container->get('content_translation.manager')->setEnabled('node', 'page', TRUE);

    // Create an entity reference field, in order to test relationship queries.
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'field_name' => 'field_reference',
      'settings' => [
        'target_type' => 'entity_test_mulrevpub',
      ],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'page',
      'field_name' => 'field_reference',
    ])->save();

    $this->enablePromotedContentView(FALSE);

    ViewTestData::createTestViews(static::class, ['views_test_config']);

    Workspace::create(['id' => 'stage', 'label' => 'Stage'])->save();
  }

  /**
   * Tests workspace query alter for views.
   *
   * @legacy-covers \Drupal\workspaces\Hook\ViewsOperations::alterQueryForEntityType
   * @legacy-covers \Drupal\workspaces\Hook\ViewsOperations::getRevisionTableJoin
   */
  public function testViewsQueryAlter(): void {
    // Create a test entity and two nodes.
    $test_entity = \Drupal::entityTypeManager()
      ->getStorage('entity_test_mulrevpub')
      ->create(['name' => 'test entity - live']);
    $test_entity->save();
    $node_1 = $this->createNode([
      'title' => 'node - live - 1',
      'body' => 'node 1',
      'created' => $this->createdTimestamp++,
      'field_reference' => $test_entity->id(),
      'promote' => TRUE,
    ]);
    $node_2 = $this->createNode([
      'title' => 'node - live - 2',
      'body' => 'node 2',
      'created' => $this->createdTimestamp++,
      'promote' => TRUE,
    ]);

    $this->switchToWorkspace('stage');

    $view = Views::getView('promoted_content');

    // Add a filter on a field that is stored in a dedicated table in order to
    // test field joins with extra conditions (e.g. 'deleted' and 'langcode').
    $view->setDisplay('page_1');
    $filters = $view->displayHandlers->get('page_1')->getOption('filters');
    $view->displayHandlers->get('page_1')->overrideOption('filters', $filters + [
      'body_value' => [
        'id' => 'body_value',
        'table' => 'node__body',
        'field' => 'body_value',
        'operator' => 'not empty',
        'plugin_id' => 'string',
      ],
    ]);
    $view->execute();
    $expected = [
      ['nid' => $node_2->id()],
      ['nid' => $node_1->id()],
    ];
    $this->assertIdenticalResultset($view, $expected, ['nid' => 'nid']);

    // Add a filter on a field from a relationship, in order to test field
    // joins with extra conditions (e.g. 'deleted' and 'langcode').
    $view->destroy();
    $view->setDisplay('page_1');
    $view->displayHandlers->get('page_1')->overrideOption('relationships', [
      'field_reference' => [
        'id' => 'field_reference',
        'table' => 'node__field_reference',
        'field' => 'field_reference',
        'required' => FALSE,
      ],
    ]);
    $view->displayHandlers->get('page_1')->overrideOption('filters', $filters + [
      'name' => [
        'id' => 'name',
        'table' => 'entity_test_mulrevpub_property_data',
        'field' => 'name',
        'operator' => 'not empty',
        'relationship' => 'field_reference',
      ],
    ]);
    $view->execute();

    $expected = [
      ['nid' => $node_1->id()],
    ];
    $this->assertIdenticalResultset($view, $expected, ['nid' => 'nid']);
  }

  /**
   * Tests sorting a view by a revisionable field in a workspace.
   */
  public function testViewsSortByField(): void {
    $nodes = $this->createNodesEditedInWorkspace();

    $view = Views::getView('promoted_content');
    $view->setDisplay('page_1');
    $display = $view->displayHandlers->get('page_1');
    $display->overrideOption('filters', []);
    // A standard sort selects the field, so the ORDER BY follows the same
    // rewrite as fields and filters.
    $display->overrideOption('sorts', [
      'title' => [
        'id' => 'title',
        'table' => 'node_field_data',
        'field' => 'title',
        'entity_type' => 'node',
        'entity_field' => 'title',
        'plugin_id' => 'standard',
        'order' => 'ASC',
      ],
    ]);
    $view->execute();

    // In the workspace node A's title is 'Z', so ascending order is B, C, A.
    $this->assertIdenticalResultset($view, [
      ['nid' => $nodes['b']->id()],
      ['nid' => $nodes['c']->id()],
      ['nid' => $nodes['a']->id()],
    ], ['nid' => 'nid']);
  }

  /**
   * Tests sorting a view by a formula in a workspace.
   */
  public function testViewsSortByFormula(): void {
    $nodes = $this->createNodesEditedInWorkspace();

    $view = Views::getView('promoted_content');
    $view->setDisplay('page_1');
    $display = $view->displayHandlers->get('page_1');
    $display->overrideOption('filters', []);
    // A date sort with a granularity coarser than 'second' sorts by a formula
    // that references the column directly, so the reference is rewritten inside
    // the formula.
    $display->overrideOption('sorts', [
      'created' => [
        'id' => 'created',
        'table' => 'node_field_data',
        'field' => 'created',
        'entity_type' => 'node',
        'entity_field' => 'created',
        'plugin_id' => 'date',
        'granularity' => 'day',
        'order' => 'ASC',
      ],
    ]);
    $view->execute();

    // In the workspace node A has the earliest creation date, so ascending
    // order by day is A, C, B.
    $this->assertIdenticalResultset($view, [
      ['nid' => $nodes['a']->id()],
      ['nid' => $nodes['c']->id()],
      ['nid' => $nodes['b']->id()],
    ], ['nid' => 'nid']);
  }

  /**
   * Tests a click sort with DISTINCT on a revisionable field in a workspace.
   */
  public function testViewsClickSortWithDistinct(): void {
    $nodes = $this->createNodesEditedInWorkspace();

    // A table style click sort references the column directly, e.g.
    // 'node_field_data.title', not a field alias. With DISTINCT the ORDER BY
    // column must also be in the SELECT list, so a stale reference fails.
    $view = $this->titleClickSortView();
    $view->execute();

    // In the workspace node A's title is 'Z', so ascending order is B, C, A.
    $this->assertIdenticalResultset($view, [
      ['nid' => $nodes['b']->id()],
      ['nid' => $nodes['c']->id()],
      ['nid' => $nodes['a']->id()],
    ], ['nid' => 'nid']);

    // Outside the workspace the live titles order the rows A, B, C.
    $this->switchToLive();
    $view = $this->titleClickSortView();
    $view->execute();
    $this->assertIdenticalResultset($view, [
      ['nid' => $nodes['a']->id()],
      ['nid' => $nodes['b']->id()],
      ['nid' => $nodes['c']->id()],
    ], ['nid' => 'nid']);
  }

  /**
   * Tests a filter that wraps a revisionable field in a condition object.
   */
  public function testViewsFilterConditionObject(): void {
    $nodes = $this->createNodesEditedInWorkspace();

    // The 'contains any/all words' string operator adds the field inside an
    // AND/OR condition object, instead of a plain condition string.
    $view = $this->buildNodeView('workspace_title_contains', [
      'fields' => [
        'nid' => [
          'id' => 'nid',
          'table' => 'node_field_data',
          'field' => 'nid',
          'entity_type' => 'node',
          'entity_field' => 'nid',
          'plugin_id' => 'field',
        ],
      ],
      'filters' => [
        'title' => [
          'id' => 'title',
          'table' => 'node_field_data',
          'field' => 'title',
          'entity_type' => 'node',
          'entity_field' => 'title',
          'plugin_id' => 'string',
          'operator' => 'allwords',
          'value' => 'Z',
        ],
      ],
    ]);
    $view->execute();

    // Only node A has the title 'Z' in the workspace.
    $this->assertIdenticalResultset($view, [
      ['nid' => $nodes['a']->id()],
    ], ['nid' => 'nid']);

    // Outside the workspace no node has 'Z' in its title.
    $this->switchToLive();
    $view = Views::getView('workspace_title_contains');
    $view->execute();
    $this->assertIdenticalResultset($view, [], ['nid' => 'nid']);
  }

  /**
   * Tests grouping and counting by a revisionable field in a workspace.
   */
  public function testViewsGroupByAndCount(): void {
    $this->createNodesEditedInWorkspace();

    // The GROUP BY clause is derived from the selected fields, so grouping by a
    // revisionable field follows the field rewrite to the revision table.
    $view = $this->buildNodeAggregateView('workspace_group_by_count', [
      'sticky' => $this->nodeHandlerConfig('sticky', 'boolean', 'group'),
      'nid' => $this->nodeHandlerConfig('nid', 'numeric', 'count'),
    ]);
    $view->execute();

    $counts = [];
    foreach ($view->result as $row) {
      $counts[(int) $view->field['sticky']->getValue($row)] = (int) $view->field['nid']->getValue($row);
    }
    ksort($counts);

    // In the workspace node A is sticky, so there is 1 non-sticky node (B) and
    // 2 sticky nodes (A and C).
    $this->assertSame([0 => 1, 1 => 2], $counts);
  }

  /**
   * Tests a HAVING clause on a counted base field in a workspace.
   */
  public function testViewsHavingOnCount(): void {
    $this->createNodesEditedInWorkspace();

    $view = $this->buildNodeAggregateView('workspace_having_count', [
      'sticky' => $this->nodeHandlerConfig('sticky', 'boolean', 'group'),
      'nid' => $this->nodeHandlerConfig('nid', 'numeric', 'count'),
    ], [
      'nid' => $this->nodeHandlerConfig('nid', 'numeric', 'count') + [
        'operator' => '>=',
        'value' => ['value' => 2],
      ],
    ]);
    $view->execute();

    // Only the sticky group has 2 nodes in the workspace (A and C).
    $this->assertCount(1, $view->result);
    $row = reset($view->result);
    $this->assertSame(1, (int) $view->field['sticky']->getValue($row));
    $this->assertSame(2, (int) $view->field['nid']->getValue($row));
  }

  /**
   * Tests a HAVING clause on an aggregated revisionable field in a workspace.
   */
  public function testViewsHavingOnAggregatedField(): void {
    $this->createNodesEditedInWorkspace();

    // Group by the content type, which is not revisionable, so the nodes stay
    // in a single group and the HAVING condition (which wraps the column in an
    // aggregate, e.g. SUM(sticky)) is the only thing that depends on
    // revisionable values.
    $view = $this->buildNodeAggregateView('workspace_having_sum', [
      'type' => $this->nodeHandlerConfig('type', 'field', 'group'),
    ], [
      'sticky' => $this->nodeHandlerConfig('sticky', 'numeric', 'sum') + [
        'operator' => '>=',
        'value' => ['value' => 2],
      ],
    ]);
    $view->execute();

    // In the workspace 2 of the 3 nodes are sticky, so SUM(sticky) is 2 and the
    // 'page' group passes the HAVING condition. With live values SUM(sticky) is
    // 1 and the group would be filtered out.
    $this->assertCount(1, $view->result);
  }

  /**
   * Tests the pager count query filtering on a revisionable field.
   */
  public function testViewsCountQuery(): void {
    $this->createNodesEditedInWorkspace();

    $view = Views::getView('promoted_content');
    $view->setDisplay('page_1');
    $display = $view->displayHandlers->get('page_1');
    $display->overrideOption('pager', [
      'type' => 'full',
      'options' => ['items_per_page' => 1],
    ]);
    $display->overrideOption('filters', [
      'sticky' => [
        'id' => 'sticky',
        'table' => 'node_field_data',
        'field' => 'sticky',
        'entity_type' => 'node',
        'entity_field' => 'sticky',
        'plugin_id' => 'boolean',
        'operator' => '=',
        'value' => '1',
      ],
    ]);
    $view->execute();

    // In the workspace nodes A and C are sticky, so the total is 2.
    $this->assertEquals(2, $view->total_rows);
  }

  /**
   * Creates three nodes and edits one of them in the 'stage' workspace.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The three nodes, keyed by 'a', 'b' and 'c'.
   */
  protected function createNodesEditedInWorkspace(): array {
    $day = 86400;
    $nodes = [];
    $nodes['a'] = $this->createNode(['title' => 'A', 'created' => 3 * $day, 'sticky' => FALSE, 'promote' => TRUE]);
    $nodes['b'] = $this->createNode(['title' => 'B', 'created' => 2 * $day, 'sticky' => FALSE, 'promote' => TRUE]);
    $nodes['c'] = $this->createNode(['title' => 'C', 'created' => 1 * $day, 'sticky' => TRUE, 'promote' => TRUE]);

    $this->switchToWorkspace('stage');

    $nodes['a']->setTitle('Z');
    $nodes['a']->setSticky(TRUE);
    $nodes['a']->setCreatedTime(0);
    $nodes['a']->save();

    return $nodes;
  }

  /**
   * Builds a views handler configuration for a 'node_field_data' field.
   *
   * @param string $field
   *   The field name.
   * @param string $plugin_id
   *   The views handler plugin ID.
   * @param string $group_type
   *   The aggregation type, e.g. 'group', 'count' or 'sum'.
   *
   * @return array
   *   The handler configuration.
   */
  protected function nodeHandlerConfig(string $field, string $plugin_id, string $group_type): array {
    return [
      'id' => $field,
      'table' => 'node_field_data',
      'field' => $field,
      'entity_type' => 'node',
      'entity_field' => $field,
      'plugin_id' => $plugin_id,
      'group_type' => $group_type,
    ];
  }

  /**
   * Builds and saves an aggregated view on the 'node_field_data' table.
   *
   * @param string $id
   *   The ID of the view to create.
   * @param array $fields
   *   The 'fields' handler configuration.
   * @param array $filters
   *   (optional) The 'filters' handler configuration.
   *
   * @return \Drupal\views\ViewExecutable
   *   The executable view.
   */
  protected function buildNodeAggregateView(string $id, array $fields, array $filters = []): ViewExecutable {
    $display_options = [
      'group_by' => TRUE,
      'fields' => $fields,
    ];
    if ($filters) {
      $display_options['filters'] = $filters;
    }

    return $this->buildNodeView($id, $display_options);
  }

  /**
   * Builds and saves a view on the 'node_field_data' table.
   *
   * @param string $id
   *   The ID of the view to create.
   * @param array $display_options
   *   The display options. Access, pager, style and row default to a simple
   *   fields-based view.
   *
   * @return \Drupal\views\ViewExecutable
   *   The executable view.
   */
  protected function buildNodeView(string $id, array $display_options): ViewExecutable {
    $display_options += [
      'access' => ['type' => 'none'],
      'pager' => ['type' => 'none'],
      'style' => ['type' => 'default'],
      'row' => ['type' => 'fields'],
    ];

    \Drupal::service('workspaces.manager')->executeOutsideWorkspace(function () use ($id, $display_options) {
      View::create([
        'id' => $id,
        'label' => $id,
        'base_table' => 'node_field_data',
        'base_field' => 'nid',
        'display' => [
          'default' => [
            'display_plugin' => 'default',
            'id' => 'default',
            'display_title' => 'Default',
            'position' => 0,
            'display_options' => $display_options,
          ],
        ],
      ])->save();
    });

    return Views::getView($id);
  }

  /**
   * Loads the DISTINCT click-sort test view, sorted by the title column.
   *
   * @return \Drupal\views\ViewExecutable
   *   The executable view.
   */
  protected function titleClickSortView(): ViewExecutable {
    $view = Views::getView('test_distinct_click_sorting');
    $view->setDisplay('default');
    $style = $view->displayHandlers->get('default')->getOption('style');
    $style['options']['default'] = 'title';
    $style['options']['order'] = 'asc';
    $view->displayHandlers->get('default')->overrideOption('style', $style);
    return $view;
  }

  /**
   * Tests creating a view of workspace entities.
   *
   * @see \Drupal\views\Plugin\views\wizard\WizardPluginBase
   */
  public function testCreateWorkspaceView(): void {
    $wizard = \Drupal::service('plugin.manager.views.wizard')->createInstance('standard:workspace', []);
    $form = [];
    $form_state = new FormState();
    $form = $wizard->buildForm($form, $form_state);
    $random_id = $this->randomMachineName();
    $random_label = $this->randomMachineName();

    $form_state->setValues([
      'id' => $random_id,
      'label' => $random_label,
      'base_table' => 'workspace',
    ]);

    $wizard->validateView($form, $form_state);
    $view = $wizard->createView($form, $form_state);
    $this->assertInstanceOf(ViewUI::class, $view);
    $this->assertEquals($random_id, $view->get('id'));
    $this->assertEquals($random_label, $view->get('label'));
    $this->assertEquals('workspace', $view->get('base_table'));
  }

}
