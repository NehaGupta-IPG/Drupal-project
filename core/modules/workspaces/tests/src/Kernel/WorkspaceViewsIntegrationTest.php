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
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
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
    'taxonomy',
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
    $this->installEntitySchema('entity_test_revpub');
    $this->installEntitySchema('taxonomy_term');
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

    if ($import_test_views) {
      ViewTestData::createTestViews(static::class, ['views_test_config']);
    }

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

    // A clause passed to Sql::addGroupBy() references the base table column
    // directly, so it has to be rewritten too. MIN(sticky) is an aggregate, so
    // it adds no GROUP BY clause of its own.
    $view = $this->buildNodeAggregateView('workspace_group_by_clause', [
      'sticky' => $this->nodeHandlerConfig('sticky', 'boolean', 'min'),
      'nid' => $this->nodeHandlerConfig('nid', 'numeric', 'count'),
    ]);
    $view->setDisplay();
    $view->initQuery();
    $view->query->addGroupBy('node_field_data.sticky');
    $view->execute();

    $counts = [];
    foreach ($view->result as $row) {
      $counts[(int) $view->field['sticky']->getValue($row)] = (int) $view->field['nid']->getValue($row);
    }
    ksort($counts);

    // Grouping by the live column would give [0 => 2, 1 => 1].
    $this->assertSame([0 => 1, 1 => 2], $counts);
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
   * Tests the views query alter for an entity type without a data table.
   */
  public function testViewsQueryAlterWithoutDataTable(): void {
    // This entity type has no data table, so the query reads the base table,
    // which holds revisionable and non-revisionable fields alike. Only the
    // revisionable ones (e.g. 'name') should be affected by the query alter.
    $storage = $this->entityTypeManager->getStorage('entity_test_revpub');
    $entity = $storage->create(['name' => 'live', 'non_rev_field' => 'shared']);
    $entity->save();

    $this->assertSame(['live'], $this->getRevPubViewResults('name', 'live'));
    $this->assertSame([], $this->getRevPubViewResults('name', 'stage'));

    $this->switchToWorkspace('stage');
    $entity->set('name', 'stage');
    $entity->save();

    // The pending revision holds the new name, so that is what the filter has
    // to match on, and what the field has to return.
    $this->assertSame(['stage'], $this->getRevPubViewResults('name', 'stage'));
    $this->assertSame([], $this->getRevPubViewResults('name', 'live'));

    // A non-revisionable field is shared by every revision and only exists on
    // the base table. Redirecting it to the revision table would query a column
    // that doesn't exist. The name still comes from the pending revision.
    $this->assertSame(['stage'], $this->getRevPubViewResults('non_rev_field', 'shared'));

    // Live still sees the default revision.
    $this->switchToLive();
    $this->assertSame(['live'], $this->getRevPubViewResults('name', 'live'));
    $this->assertSame([], $this->getRevPubViewResults('name', 'stage'));
  }

  /**
   * Returns the 'name' values of a view of 'entity_test_revpub' with a filter.
   *
   * @param string $field
   *   The field to filter on.
   * @param string $value
   *   The value to filter for.
   *
   * @return string[]
   *   The name of each row the view returned.
   */
  protected function getRevPubViewResults(string $field, string $value): array {
    $view = View::create([
      'id' => 'test_entity_test_revpub',
      'base_table' => 'entity_test_revpub',
      'base_field' => 'id',
    ]);
    $executable = Views::executableFactory()->get($view);
    $executable->setDisplay('default');
    $executable->addHandler('default', 'field', 'entity_test_revpub', 'name', []);
    // Views only selects the column when something sorts on it.
    $executable->addHandler('default', 'sort', 'entity_test_revpub', 'name', []);
    $executable->addHandler('default', 'filter', 'entity_test_revpub', $field, [
      'operator' => '=',
      'value' => $value,
    ]);
    $executable->execute();

    // The rendered value comes from the loaded entity, which is already the
    // pending revision, so read the selected column instead.
    return array_map(fn ($row) => (string) $row->entity_test_revpub_name, $executable->result);
  }

  /**
   * Tests the views query alter for a field stored in more than one column.
   */
  public function testViewsQueryAlterMultiColumnField(): void {
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    $term = Term::create([
      'vid' => 'tags',
      'name' => 'Test term',
      'description' => ['value' => 'live text', 'format' => 'plain_text'],
    ]);
    $term->save();

    $this->assertSame(1, $this->countTermViewResults('live text'));
    $this->assertSame(0, $this->countTermViewResults('stage text'));

    $this->switchToWorkspace('stage');
    $term->set('description', ['value' => 'stage text', 'format' => 'plain_text']);
    $term->save();

    // Views names the column 'description__value', not 'description', so the
    // filter is only redirected when the column name is matched.
    $this->assertSame(1, $this->countTermViewResults('stage text'));
    $this->assertSame(0, $this->countTermViewResults('live text'));
  }

  /**
   * Counts the rows of a term view filtered on the description column.
   *
   * @param string $value
   *   The description value to filter for.
   *
   * @return int
   *   The number of rows the view returned.
   */
  protected function countTermViewResults(string $value): int {
    $view = View::create([
      'id' => 'test_taxonomy_term',
      'base_table' => 'taxonomy_term_field_data',
      'base_field' => 'tid',
    ]);
    $executable = Views::executableFactory()->get($view);
    $executable->setDisplay('default');
    $executable->addHandler('default', 'filter', 'taxonomy_term_field_data', 'description__value', [
      'operator' => '=',
      'value' => $value,
    ]);
    $executable->execute();

    return count($executable->result);
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
