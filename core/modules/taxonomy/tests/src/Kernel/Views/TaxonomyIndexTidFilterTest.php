<?php

declare(strict_types=1);

namespace Drupal\Tests\taxonomy\Kernel\Views;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\views\Entity\View;
use Drupal\views\Tests\ViewTestData;
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
    $display =& $view->getDisplay('default');
    $display['display_options']['filters']['tid']['value'][0] = $this->terms[3]->uuid();
    $display['display_options']['filters']['tid']['value'][1] = $this->terms[4]->uuid();
    $view->save();

    // Dependencies are sorted.
    $content_dependencies = [
      'taxonomy_term:tags:' . $this->terms[3]->uuid(),
      'taxonomy_term:tags:' . $this->terms[4]->uuid(),
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
        'taxonomy_term:tags:' . $this->terms[4]->uuid(),
      ],
      'module' => [
        'node',
        'taxonomy',
        'user',
      ],
    ], $view->calculateDependencies()->getDependencies());
  }

}
