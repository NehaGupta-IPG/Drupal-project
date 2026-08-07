<?php

declare(strict_types=1);

namespace Drupal\Tests\taxonomy\Kernel\Views;

use Drupal\Core\Form\FormState;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\views\Entity\View;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the taxonomy term index filter.
 *
 * @see \Drupal\taxonomy\Plugin\views\filter\TaxonomyIndexTid
 */
#[Group('taxonomy')]
#[RunTestsInSeparateProcesses]
class TaxonomyIndexTidFilterTest extends TaxonomyTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_filter_taxonomy_index_tid__non_existing_dependency'];

  /**
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected $terms = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp(FALSE);

    // Setup vocabulary and terms so the initial import is valid.
    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();

    // This will get a term ID of 3.
    $term = Term::create([
      'vid' => 'tags',
      'name' => 'muh',
    ]);
    $term->save();
    // This will get a term ID of 4.
    $this->terms[$term->id()] = $term;
    $term = Term::create([
      'vid' => 'tags',
      'name' => 'muh',
    ]);
    $term->save();
    $this->terms[$term->id()] = $term;

    ViewTestData::createTestViews(static::class, ['taxonomy_test_views']);
  }

  /**
   * Tests dependencies are not added for terms that do not exist.
   */
  public function testConfigDependency(): void {
    /** @var \Drupal\views\Entity\View $view */
    $view = View::load('test_filter_taxonomy_index_tid__non_existing_dependency');

    // Dependencies are sorted.
    $content_dependencies = [
      $this->terms[3]->getConfigDependencyName(),
      $this->terms[4]->getConfigDependencyName(),
    ];
    sort($content_dependencies);

    $this->assertEquals([
      'config' => [
        'taxonomy.vocabulary.tags',
      ],
      'content' => $content_dependencies,
      'module' => [
        'node',
        'taxonomy',
        'user',
      ],
    ], $view->calculateDependencies()->getDependencies());

    $this->terms[3]->delete();

    $this->assertEquals([
      'config' => [
        'taxonomy.vocabulary.tags',
      ],
      'content' => [
        $this->terms[4]->getConfigDependencyName(),
      ],
      'module' => [
        'node',
        'taxonomy',
        'user',
      ],
    ], $view->calculateDependencies()->getDependencies());
  }

  /**
   * Tests that filter options spanning vocabularies are ordered by weight.
   */
  public function testValueFormVocabularyWeightOrdering(): void {
    // Terms in a lighter vocabulary should sort first, preserving their
    // own weight/name order.
    Vocabulary::create([
      'vid' => 'lighter',
      'name' => 'Lighter',
      'weight' => -10,
    ])->save();
    $lighter_term = Term::create([
      'vid' => 'lighter',
      'name' => 'zzz',
      'weight' => 0,
    ]);
    $lighter_term->save();
    $lighter_term2 = Term::create([
      'vid' => 'lighter',
      'name' => 'aaa',
      'weight' => 1,
    ]);
    $lighter_term2->save();

    // Terms in a heavier vocabulary should sort last.
    Vocabulary::create([
      'vid' => 'heavier',
      'name' => 'Heavier',
      'weight' => 10,
    ])->save();
    $heavier_term = Term::create([
      'vid' => 'heavier',
      'name' => 'aaa',
    ]);
    $heavier_term->save();

    $view = Views::getView('test_filter_taxonomy_index_tid__non_existing_dependency');
    $view->preview();
    $filter = $view->filter['tid'];
    // Force the code path that spans all vocabularies.
    $filter->options['limit'] = FALSE;
    $filter->options['type'] = 'select';
    $filter->options['hierarchy'] = FALSE;

    // Build the value subform.
    $form = [];
    $filter->buildOptionsForm($form, new FormState());

    // Confirm that terms in the lighter vocabulary sort first, and terms in
    // the heavier vocabulary sort last while keepting the sort order within
    // the vocabulary.
    $ordered_tids = array_map('intval', array_keys($form['value']['#options']));
    $this->assertSame([
      (int) $lighter_term->id(),
      (int) $lighter_term2->id(),
    ], array_slice($ordered_tids, 0, 2));
    $this->assertSame((int) $heavier_term->id(), end($ordered_tids));
  }

}
