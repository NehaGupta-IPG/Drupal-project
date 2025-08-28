<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Handler;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\user\UserInterface;
use Drupal\views\Plugin\views\filter\EntityReference;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Tests the core Drupal\views\Plugin\views\filter\EntityReference handler.
 *
 * @group views
 */
class FilterEntityReferenceTest extends ViewsKernelTestBase {

  use ContentTypeCreationTrait;
  use EntityReferenceFieldCreationTrait;
  use NodeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_filter_entity_reference'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'node',
    'user',
    'field',
    'text',
    'filter',
    'views',
    'views_test_entity_reference',
  ];

  /**
   * Test host nodes containing the entity reference.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected array $hostNodes;

  /**
   * Test target nodes referenced by the entity reference.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected array $targetNodes;

  /**
   * First test user as node author.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user1;

  /**
   * Second test user as node author.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user2;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp(FALSE);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['node', 'user', 'filter']);

    ViewTestData::createTestViews(static::class, ['views_test_config']);
    // Create two node types.
    $this->createContentType(['type' => 'page']);
    $this->createContentType(['type' => 'article']);

    // Create users.
    $this->user1 = $this->createUser();
    $this->user2 = $this->createUser();

    // Create target nodes to be referenced.
    foreach (range(0, 5) as $count) {
      $this->targetNodes[$count] = $this->createNode([
        'type' => 'article',
        'title' => 'Article ' . $count,
        'status' => 1,
        'uid' => $this->user1,
      ]);
    }
  }

  /**
   * Tests that results are successfully filtered by the select list widget.
   *
   * - Tests Nodes with a multi value Entity Reference field.
   * - Tests both single value and multi value filter widget.
   */
  public function testMultiValueFieldViewEntityReferenceAsSelectList(): void {
    $this->createMultiValueEntityReferenceFieldAndContent();

    // MULTIPLE VALUE FILTER.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [$this->targetNodes[0]->id()]
    );
    // Expect to have only Page 0, with Article 0 referenced.
    $expected = [
      ['title' => 'Page 0'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // Change to both Article 0 and Article 3.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [
        $this->targetNodes[0]->id(),
        $this->targetNodes[3]->id(),
      ]
    );
    // Expect to have Page 0 and 1, with Article 0 and 3 referenced.
    $expected = [
      ['title' => 'Page 0'],
      ['title' => 'Page 1'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // SINGLE VALUE FILTER.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      $this->targetNodes[3]->id(),
      EntityReference::WIDGET_SELECT,
      FALSE,
      NULL,
      FALSE
    );
    // Expect to have Page 1, with Article 3 referenced.
    $expected = [
      ['title' => 'Page 1'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);
  }

  /**
   * Tests results filtered by the select list widget with operator exposed.
   *
   * - Tests Nodes with a multi value Entity Reference field.
   * - Tests both single value and multi value filter widget.
   */
  public function testMultiValueFieldViewEntityReferenceAsSelectListExposedOperator(): void {
    $this->createMultiValueEntityReferenceFieldAndContent();
    // MULTIPLE VALUE FILTER.
    // Test `or` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [
        $this->targetNodes[0]->id(),
        $this->targetNodes[2]->id(),
      ],
      EntityReference::WIDGET_SELECT,
      TRUE,
      'or',
    );
    $expected = [
      ['title' => 'Page 0'],
      ['title' => 'Page 1'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // Test `and` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [
        $this->targetNodes[0]->id(),
        $this->targetNodes[1]->id(),
      ],
      EntityReference::WIDGET_SELECT,
      TRUE,
      'and',
    );
    $expected = [
      ['title' => 'Page 0'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // Test `not` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [
        $this->targetNodes[3]->id(),
      ],
      EntityReference::WIDGET_SELECT,
      TRUE,
      'not',
    );
    $expected = [
      ['title' => 'Page 0'],
      ['title' => 'Page 2'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // Test `empty` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [],
      EntityReference::WIDGET_SELECT,
      TRUE,
      'empty',
    );
    $expected = [
      ['title' => 'Page 2'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // Test `not empty` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [],
      EntityReference::WIDGET_SELECT,
      TRUE,
      'not empty',
    );
    // A row is returned for each field value. A site builder would need to
    // enable the `Distinct` query setting to only return one row per node.
    // @todo Does this need to be fixed?
    $expected = [
      ['title' => 'Page 0'],
      ['title' => 'Page 0'],
      ['title' => 'Page 1'],
      ['title' => 'Page 1'],
      ['title' => 'Page 1'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // SINGLE VALUE FILTER.
    // Test `or` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      $this->targetNodes[2]->id(),
      EntityReference::WIDGET_SELECT,
      TRUE,
      'or',
      FALSE
    );
    $expected = [
      ['title' => 'Page 1'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);
  }

  /**
   * Tests results filtered by the select list widget with operator exposed.
   *
   * - Tests Nodes with a single value Entity Reference field.
   * - Tests both single value and multi value filter widget.
   */
  public function testSingleValueFieldViewEntityReferenceAsSelectExposedOperator(): void {
    $this->createSingleValueEntityReferenceFieldAndContent();

    // Test `or` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [
        $this->targetNodes[0]->id(),
        $this->targetNodes[2]->id(),
      ],
      EntityReference::WIDGET_SELECT,
      TRUE,
      'or',
    );
    $expected = [
      ['title' => 'Page 0'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // Test `and` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [
        $this->targetNodes[0]->id(),
        $this->targetNodes[1]->id(),
      ],
      EntityReference::WIDGET_SELECT,
      TRUE,
      'and',
    );
    $expected = [];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // Test `not` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [
        $this->targetNodes[3]->id(),
      ],
      EntityReference::WIDGET_SELECT,
      TRUE,
      'not',
    );
    $expected = [
      ['title' => 'Page 0'],
      ['title' => 'Page 1'],
      ['title' => 'Page 2'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // Test `empty` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [],
      EntityReference::WIDGET_SELECT,
      TRUE,
      'empty',
    );
    $expected = [
      ['title' => 'Page 2'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // Test `not empty` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [],
      EntityReference::WIDGET_SELECT,
      TRUE,
      'not empty',
    );
    $expected = [
      ['title' => 'Page 0'],
      ['title' => 'Page 1'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // SINGLE VALUE FILTER.
    // Test `or` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      $this->targetNodes[1]->id(),
      EntityReference::WIDGET_SELECT,
      TRUE,
      'or',
      FALSE
    );
    $expected = [
      ['title' => 'Page 1'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);
  }

  /**
   * Tests that results are successfully filtered by the autocomplete widget.
   *
   * - Tests Nodes with a multi value Entity Reference field.
   * - Tests both single value and multi value filter widget.
   */
  public function testMultiValueFieldViewEntityReferenceAsAutocomplete(): void {
    $this->createMultiValueEntityReferenceFieldAndContent();
    // MULTIPLE VALUE FILTER.
    // Change the widget to autocomplete.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [
        ['target_id' => $this->targetNodes[0]->id()],
        ['target_id' => $this->targetNodes[3]->id()],
      ],
      EntityReference::WIDGET_AUTOCOMPLETE
    );
    // Expect to have Page 0 and 1, with Article 0 and 3 referenced.
    $expected = [
      ['title' => 'Page 0'],
      ['title' => 'Page 1'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // SINGLE VALUE FILTER.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [['target_id' => $this->targetNodes[2]->id()]],
      EntityReference::WIDGET_AUTOCOMPLETE,
      FALSE,
      NULL,
      FALSE
    );
    // Expect to have Page 1, with Article 3 referenced.
    $expected = [
      ['title' => 'Page 1'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);
  }

  /**
   * Tests results filtered by the autocomplete widget with operator exposed.
   *
   * - Tests Nodes with a multi value Entity Reference field.
   * - Tests both single value and multi value filter widget.
   */
  public function testMultiValueFieldViewEntityReferenceAsAutocompleteExposedOperator(): void {
    $this->createMultiValueEntityReferenceFieldAndContent();
    // MULTIPLE VALUE FILTER.
    // Test `or` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [
        ['target_id' => $this->targetNodes[0]->id()],
        ['target_id' => $this->targetNodes[2]->id()],
      ],
      EntityReference::WIDGET_AUTOCOMPLETE,
      TRUE,
      'or',
    );
    $expected = [
      ['title' => 'Page 0'],
      ['title' => 'Page 1'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // Test `and` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [
        ['target_id' => $this->targetNodes[0]->id()],
        ['target_id' => $this->targetNodes[1]->id()],
      ],
      EntityReference::WIDGET_AUTOCOMPLETE,
      TRUE,
      'and',
    );
    $expected = [
      ['title' => 'Page 0'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // Test `not` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [
        ['target_id' => $this->targetNodes[3]->id()],
      ],
      EntityReference::WIDGET_AUTOCOMPLETE,
      TRUE,
      'not',
    );
    $expected = [
      ['title' => 'Page 0'],
      ['title' => 'Page 2'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // Test `empty` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      NULL,
      EntityReference::WIDGET_AUTOCOMPLETE,
      TRUE,
      'empty',
    );
    $expected = [
      ['title' => 'Page 2'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // Test `not empty` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      NULL,
      EntityReference::WIDGET_AUTOCOMPLETE,
      TRUE,
      'not empty',
    );
    // A row is returned for each field value. A site builder would need to
    // enable the `Distinct` query setting to only return one row per node.
    // @todo Does this need to be fixed?
    $expected = [
      ['title' => 'Page 0'],
      ['title' => 'Page 0'],
      ['title' => 'Page 1'],
      ['title' => 'Page 1'],
      ['title' => 'Page 1'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // SINGLE VALUE FILTER.
    // Test `or` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [
        ['target_id' => $this->targetNodes[0]->id()],
      ],
      EntityReference::WIDGET_AUTOCOMPLETE,
      TRUE,
      'or',
      FALSE
    );
    $expected = [
      ['title' => 'Page 0'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);
  }

  /**
   * Tests results filtered by the autocomplete widget with operator exposed.
   *
   * - Tests Nodes with a single value Entity Reference field.
   * - Tests both single value and multi value filter widget.
   */
  public function testSingleValueFieldViewEntityReferenceAsAutoCompleteExposedOperator(): void {
    $this->createSingleValueEntityReferenceFieldAndContent();

    // Test `or` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [
        ['target_id' => $this->targetNodes[0]->id()],
        ['target_id' => $this->targetNodes[2]->id()],
      ],
      EntityReference::WIDGET_AUTOCOMPLETE,
      TRUE,
      'or',
    );
    $expected = [
      ['title' => 'Page 0'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // Test `and` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [
        ['target_id' => $this->targetNodes[0]->id()],
        ['target_id' => $this->targetNodes[1]->id()],
      ],
      EntityReference::WIDGET_AUTOCOMPLETE,
      TRUE,
      'and',
    );
    $expected = [];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // Test `not` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [
        ['target_id' => $this->targetNodes[3]->id()],
      ],
      EntityReference::WIDGET_AUTOCOMPLETE,
      TRUE,
      'not',
    );
    $expected = [
      ['title' => 'Page 0'],
      ['title' => 'Page 1'],
      ['title' => 'Page 2'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // Test `empty` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      NULL,
      EntityReference::WIDGET_AUTOCOMPLETE,
      TRUE,
      'empty',
    );
    $expected = [
      ['title' => 'Page 2'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // Test `not empty` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      NULL,
      EntityReference::WIDGET_AUTOCOMPLETE,
      TRUE,
      'not empty',
    );
    $expected = [
      ['title' => 'Page 0'],
      ['title' => 'Page 1'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // SINGLE VALUE FILTER.
    // Test `or` operator.
    $view = $this->buildAndExecuteViewWithEntityReferenceFilter(
      [
        ['target_id' => $this->targetNodes[0]->id()],
      ],
      EntityReference::WIDGET_AUTOCOMPLETE,
      TRUE,
      'or',
      FALSE
    );
    $expected = [
      ['title' => 'Page 0'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);
  }

  /**
   * Tests filtered results for autocomplete widget switched from select.
   *
   * - Tests Nodes with a multi value Entity Reference field.
   * - Tests both single value and multi value filter widget.
   */
  public function testMultiValueFieldViewEntityReferenceAsAutocompleteFromSelect(): void {
    $this->createMultiValueEntityReferenceFieldAndContent();
    // MULTIPLE VALUE FILTER.
    $view = Views::getView('test_filter_entity_reference');
    $view->setDisplay();
    // Set list max to be small to ensure it switches to autocomplete.
    $filters = $view->displayHandlers->get('default')->getOption('filters');
    $filters['field_test_target_id']['list_max'] = '3';
    $view->displayHandlers->get('default')->overrideOption('filters', $filters);
    $view->setExposedInput([
      'field_test_target_id' => [
        ['target_id' => $this->targetNodes[0]->id()],
        ['target_id' => $this->targetNodes[3]->id()],
      ],
    ]);
    $this->executeView($view);

    // Expect to have Page 0 and 1, with Article 0 and 3 referenced.
    $expected = [
      ['title' => 'Page 0'],
      ['title' => 'Page 1'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // SINGLE VALUE FILTER.
    $view = Views::getView('test_filter_entity_reference');
    $view->setDisplay();
    // Set list max to be small to ensure it switches to autocomplete.
    $filters = $view->displayHandlers->get('default')->getOption('filters');
    $filters['field_test_target_id']['list_max'] = '3';
    $filters['field_test_target_id']['expose']['multiple'] = FALSE;
    $view->displayHandlers->get('default')->overrideOption('filters', $filters);
    $view->setExposedInput([
      'field_test_target_id' => [
        ['target_id' => $this->targetNodes[0]->id()],
      ],
    ]);
    $this->executeView($view);

    // Expect to have Page 0, with Article 0 referenced.
    $expected = [
      ['title' => 'Page 0'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);
  }

  /**
   * Tests filtered results for autocomplete widget switched from select.
   *
   * - Tests Nodes with a single value Entity Reference field.
   * - Tests both single value and multi value filter widget.
   */
  public function testSingleValueFieldViewEntityReferenceAsAutocompleteFromSelect(): void {
    $this->createSingleValueEntityReferenceFieldAndContent();
    // MULTIPLE VALUE FILTER.
    $view = Views::getView('test_filter_entity_reference');
    $view->setDisplay();
    // Set list max to be small to ensure it switches to autocomplete.
    $filters = $view->displayHandlers->get('default')->getOption('filters');
    $filters['field_test_target_id']['list_max'] = '3';
    $filters['field_test_target_id']['expose']['use_operator'] = TRUE;
    $view->displayHandlers->get('default')->overrideOption('filters', $filters);
    $view->setExposedInput([
      'field_test_target_id_op' => 'or',
      'field_test_target_id' => [
        ['target_id' => $this->targetNodes[0]->id()],
        ['target_id' => $this->targetNodes[3]->id()],
      ],
    ]);
    $this->executeView($view);

    // Expect to have Page 0, with Article 0 referenced.
    $expected = [
      ['title' => 'Page 0'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);

    // SINGLE VALUE FILTER.
    $view = Views::getView('test_filter_entity_reference');
    $view->setDisplay();
    // Set list max to be small to ensure it switches to autocomplete.
    $filters = $view->displayHandlers->get('default')->getOption('filters');
    $filters['field_test_target_id']['list_max'] = '3';
    $filters['field_test_target_id']['expose']['multiple'] = FALSE;
    $filters['field_test_target_id']['expose']['use_operator'] = TRUE;
    $view->displayHandlers->get('default')->overrideOption('filters', $filters);
    $view->setExposedInput([
      'field_test_target_id_op' => 'or',
      'field_test_target_id' => [
        ['target_id' => $this->targetNodes[1]->id()],
      ],
    ]);
    $this->executeView($view);

    // Expect to have Page 1, with Article 1 referenced.
    $expected = [
      ['title' => 'Page 1'],
    ];
    $this->assertIdenticalResultset($view, $expected, [
      'title' => 'title',
    ]);
  }

  /**
   * Tests that content dependencies are added to the view.
   */
  public function testViewContentDependencies(): void {
    $this->createMultiValueEntityReferenceFieldAndContent();
    $view = Views::getView('test_filter_entity_reference');
    $value = [
      $this->targetNodes[0]->id(),
      $this->targetNodes[3]->id(),
    ];
    $view->setHandlerOption(
      'default',
      'filter',
      'field_test_target_id',
      'value',
      $value
    );

    // Dependencies are sorted.
    $content_dependencies = [
      $this->targetNodes[0]->getConfigDependencyName(),
      $this->targetNodes[3]->getConfigDependencyName(),
    ];
    sort($content_dependencies);

    $this->assertEquals([
      'config' => [
        'node.type.page',
      ],
      'content' => $content_dependencies,
      'module' => [
        'node',
        'user',
      ],
    ], $view->getDependencies());
  }

  /**
   * Adds a multi-value Entity Reference field to Page and creates host nodes.
   */
  private function createMultiValueEntityReferenceFieldAndContent(): void {
    // Add an entity reference field to the page type referencing the article
    // type.
    $selection_handler_settings = [
      'target_bundles' => [
        'article' => 'article',
      ],
    ];
    $this->createEntityReferenceField('node', 'page', 'field_test', 'Test reference', 'node', 'default', $selection_handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    // Create a page referencing Article 0 and Article 1.
    $this->hostNodes[0] = $this->createNode([
      'type' => 'page',
      'title' => 'Page 0',
      'status' => 1,
      'created' => time(),
      'field_test' => [
        $this->targetNodes[0]->id(),
        $this->targetNodes[1]->id(),
      ],
      'uid' => $this->user2,
    ]);

    // Create a page referencing Article 1, Article 2, and Article 3.
    $this->hostNodes[1] = $this->createNode([
      'type' => 'page',
      'title' => 'Page 1',
      'status' => 1,
      'created' => time() - 100,
      'field_test' => [
        $this->targetNodes[1]->id(),
        $this->targetNodes[2]->id(),
        $this->targetNodes[3]->id(),
      ],
      'uid' => $this->user2,
    ]);

    // Create a page referencing nothing.
    $this->hostNodes[2] = $this->createNode([
      'type' => 'page',
      'title' => 'Page 2',
      'status' => 1,
      'created' => time() - 200,
      'uid' => $this->user2,
    ]);
  }

  /**
   * Adds a single-value Entity Reference field to Page and creates host nodes.
   */
  private function createSingleValueEntityReferenceFieldAndContent(): void {
    // Add an entity reference field to the page type referencing the article
    // type.
    $selection_handler_settings = [
      'target_bundles' => [
        'article' => 'article',
      ],
    ];
    $this->createEntityReferenceField('node', 'page', 'field_test', 'Test reference', 'node', 'default', $selection_handler_settings);

    // Create a page referencing Article 0.
    $this->hostNodes[0] = $this->createNode([
      'type' => 'page',
      'title' => 'Page 0',
      'status' => 1,
      'created' => time(),
      'field_test' => $this->targetNodes[0]->id(),
      'uid' => $this->user2,
    ]);

    // Create a page referencing Article 1.
    $this->hostNodes[1] = $this->createNode([
      'type' => 'page',
      'title' => 'Page 1',
      'status' => 1,
      'created' => time() - 100,
      'field_test' => $this->targetNodes[1]->id(),
      'uid' => $this->user2,
    ]);

    // Create a page referencing nothing.
    $this->hostNodes[2] = $this->createNode([
      'type' => 'page',
      'title' => 'Page 2',
      'status' => 1,
      'created' => time() - 200,
      'uid' => $this->user2,
    ]);
  }

  /**
   * Builds and executes the View with the Entity Reference filter.
   *
   * View: test_filter_entity_reference.
   *
   * @param array|string|null $filter_input
   *   The value to set the Entity Reference filter to.
   * @param string $widget
   *   The Entity Reference filter widget used. Default: select.
   * @param bool $expose_use_operator
   *   If the filter operator should be exposed. Default: FALSE.
   * @param string|null $field_test_target_id_op
   *   The value to set the exposed operator to. Default: NULL. Must be provided
   *   if $expose_use_operator is TRUE.
   * @param bool $expose_multiple
   *   If the exposed filter widget should accept multiple values or not.
   *   Default: TRUE.
   *
   * @return \Drupal\views\ViewExecutable
   *   The built and executed View Executable.
   */
  private function buildAndExecuteViewWithEntityReferenceFilter(
    array|string|null $filter_input,
    string $widget = EntityReference::WIDGET_SELECT,
    bool $expose_use_operator = FALSE,
    string|null $field_test_target_id_op = NULL,
    bool $expose_multiple = TRUE,
  ): ViewExecutable {
    $view = Views::getView('test_filter_entity_reference');
    $view->setDisplay();
    $filters = $view->displayHandlers->get('default')->getOption('filters');
    $filters['field_test_target_id']['expose']['multiple'] = $expose_multiple;
    $filters['field_test_target_id']['expose']['use_operator'] = $expose_use_operator;
    $filters['field_test_target_id']['widget'] = $widget;
    $view->displayHandlers->get('default')->overrideOption('filters', $filters);
    $input['field_test_target_id'] = $filter_input;
    if ($expose_use_operator) {
      if (NULL === $field_test_target_id_op) {
        throw new \LogicException('A string value must be provided for `$field_test_target_id_op` if `$expose_use_operator` is TRUE.');
      }
      $input['field_test_target_id_op'] = $field_test_target_id_op;
    }
    $view->setExposedInput($input);
    $this->executeView($view);
    return $view;
  }

}
