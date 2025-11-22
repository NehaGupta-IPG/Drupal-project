<?php

namespace Drupal\Tests\views\Kernel;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\views\Views;

/**
 * Tests the many to one filter.
 *
 * @group views
 */
class ManyToOneFilterTest extends ViewsKernelTestBase {

  use TaxonomyTestTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $testViews = [];

  /**
   * Stores the terms used in the tests.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected $terms = [];

  /**
   * Stores the nodes used in the tests.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $nodes = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'text',
    'filter',
    'field',
    'taxonomy',
    'node',
    'views',
    'views_test_many_to_one',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_vocabulary');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig([
      'user',
      'filter',
      'views_test_many_to_one',
    ]);
    $vocabulary = Vocabulary::load('tags');
    for ($i = 0; $i < 3; $i++) {
      $term = $this->createTerm($vocabulary);
      $this->terms[] = $term;
      $this->nodes = $this->createNode([
        'type' => 'article',
        'field_tags' => [
          [
            'target_id' => $term->id(),
          ],
        ],
      ]);
    }
    $this->nodes = $this->createNode([
      'type' => 'article',
      'field_tags' => [
        [
          'target_id' => $this->terms[0]->id(),
        ],
        [
          'target_id' => $this->terms[1]->id(),
        ],
      ],
    ]);
    $this->nodes = $this->createNode([
      'type' => 'article',
      'field_tags' => [
        [
          'target_id' => $this->terms[0]->id(),
        ],
        [
          'target_id' => $this->terms[1]->id(),
        ],
        [
          'target_id' => $this->terms[2]->id(),
        ],
      ],
    ]);
  }

  /**
   * Tests filters.
   */
  public function testFilters(): void {
    $filter_values = [
      $this->terms[0]->id(),
      $this->terms[1]->id(),
    ];

    $view = Views::getView('test_many_to_one_exposed');
    $view->initHandlers();
    $view->filter['field_tags_target_id']->value = $filter_values;
    $view->filter['field_tags_target_id']->operator = 'or';
    $this->executeView($view);
    $this->assertCount(4, $view->result);

    // Recreate the view so the next operator runs a fresh query.
    $view = Views::getView('test_many_to_one_exposed');
    $view->initHandlers();
    $view->filter['field_tags_target_id']->value = $filter_values;
    $view->filter['field_tags_target_id']->operator = 'and';
    $this->executeView($view);
    $this->assertCount(2, $view->result);

    $view = Views::getView('test_many_to_one_exposed');
    $view->initHandlers();
    $view->filter['field_tags_target_id']->value = $filter_values;
    $view->filter['field_tags_target_id']->operator = 'not';
    $this->executeView($view);
    $this->assertCount(1, $view->result);
  }

  /**
   * Tests contexts.
   */
  public function testContexts(): void {
    $view = Views::getView('test_many_to_one_contextual');
    $view->initHandlers();
    $args = [$this->terms[0]->id() . "+" . $this->terms[1]->id()];
    $this->executeView($view, $args);
    $this->assertCount(4, $view->result);

    // Recreate the view so the second argument combination executes again.
    $view = Views::getView('test_many_to_one_contextual');
    $view->initHandlers();
    $args = [$this->terms[0]->id() . "," . $this->terms[1]->id()];
    $this->executeView($view, $args);
    $this->assertCount(2, $view->result);
  }

}
