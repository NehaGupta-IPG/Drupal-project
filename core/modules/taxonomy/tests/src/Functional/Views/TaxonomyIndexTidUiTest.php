<?php

declare(strict_types=1);

namespace Drupal\Tests\taxonomy\Functional\Views;

use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\views_ui\Functional\UITestBase;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Entity\View;

/**
 * Tests the taxonomy index filter handler UI.
 *
 * @group taxonomy
 * @see \Drupal\taxonomy\Plugin\views\field\TaxonomyIndexTid
 */
class TaxonomyIndexTidUiTest extends UITestBase {

  use EntityReferenceFieldCreationTrait;

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = [
    'test_filter_taxonomy_index_tid',
    'test_taxonomy_term_name',
    'test_taxonomy_exposed_grouped_filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'taxonomy',
    'views',
    'views_ui',
    'taxonomy_test_views',
  ];

  /**
   * A nested array of \Drupal\taxonomy\TermInterface objects.
   *
   * @var \Drupal\taxonomy\TermInterface[][]
   */
  protected $terms = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = []): void {
    parent::setUp($import_test_views, $modules);

    $this->adminUser = $this->drupalCreateUser([
      'administer taxonomy',
      'administer views',
    ]);
    $this->drupalLogin($this->adminUser);

    $this->terms = $this->createVocabularyAndTerms('tags');

    Vocabulary::create([
      'vid' => 'other_tags',
      'name' => 'Other tags',
    ])->save();

    Vocabulary::create([
      'vid' => 'empty_vocabulary',
      'name' => 'Empty Vocabulary',
    ])->save();

    ViewTestData::createTestViews(get_class($this), ['taxonomy_test_views']);
  }

  /**
   * Creates a vocabulary and terms for it, ensuring unique term names within the vocabulary.
   *
   * @param string $vocab_id
   *   The vocabulary ID.
   *
   * @return \Drupal\taxonomy\TermInterface[][]
   *   The terms.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createVocabularyAndTerms(string $vocab_id): array {
    // Load the vocabulary or create it if it doesn't exist.
    $vocab = Vocabulary::load($vocab_id);
    if (!$vocab) {
      $vocab = Vocabulary::create([
        'vid' => $vocab_id,
        'name' => 'Test Vocabulary ' . $vocab_id,
      ]);
      $vocab->save();
    }

    // Initialize a local array to store terms.
    $terms = [];

    // Setup a hierarchy with unique term names.
    for ($i = 0; $i < 3; $i++) {
      for ($j = 0; $j <= $i; $j++) {
        $term_name = "Term $i.$j $vocab_id";

        // Check if the term already exists in the vocabulary.
        $existing_term = \Drupal::entityTypeManager()
          ->getStorage('taxonomy_term')
          ->loadByProperties(['name' => $term_name, 'vid' => $vocab_id]);

        // If the term does not exist, create it.
        if (empty($existing_term)) {
          $parent_term_id = isset($terms[$i][0]) ? $terms[$i][0]->id() : 0;
          $terms[$i][$j] = Term::create([
            'vid' => $vocab_id,
            'name' => $term_name,
            'parent' => $parent_term_id,
          ]);
          $terms[$i][$j]->save();
        }
        else {
          // If the term exists, load the first match.
          $terms[$i][$j] = reset($existing_term);
        }
      }
    }

    // Create a standalone term for Term 3.0
    $term_name = "Term 3.0";

    // Check if the term already exists.
    $existing_term = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => $term_name, 'vid' => $vocab_id]);

    if (empty($existing_term)) {
      $terms[3][0] = Term::create([
        'vid' => $vocab_id,
        'name' => $term_name,
      ]);
      $terms[3][0]->save();
    }
    else {
      $terms[3][0] = reset($existing_term);
    }

    return $terms;
  }

  /**
   * Tests the filter UI.
   *
   * @group legacy
   */
  public function testFilterUI(): void {
    $this->drupalGet('admin/structure/views/nojs/handler/test_filter_taxonomy_index_tid/default/filter/tid');

    $result = $this->assertSession()->selectExists('edit-options-value')->findAll('css', 'option');

    // Ensure that the expected hierarchy is available in the UI.
    $counter = 0;
    for ($i = 0; $i < 3; $i++) {
      for ($j = 0; $j <= $i; $j++) {
        $option = $result[$counter++];
        $prefix = $this->terms[$i][$j]->parent->target_id ? '-' : '';
        $tid = $option->getAttribute('value');

        $this->assertEquals($prefix . $this->terms[$i][$j]->getName(), $option->getText());
        $this->assertEquals($this->terms[$i][$j]->id(), $tid);
      }
    }

    // Ensure the autocomplete input element appears when using the 'textfield'
    // type.
    $view = View::load('test_filter_taxonomy_index_tid');
    $display =& $view->getDisplay('default');
    $display['display_options']['filters']['tid']['type'] = 'textfield';
    $view->save();
    $this->drupalGet('admin/structure/views/nojs/handler/test_filter_taxonomy_index_tid/default/filter/tid');
    $this->assertSession()->fieldExists('edit-options-value');

    // Tests \Drupal\taxonomy\Plugin\views\filter\TaxonomyIndexTid::calculateDependencies().
    $expected = [
      'config' => [
        'taxonomy.vocabulary.tags',
      ],
      'content' => [
        'taxonomy_term:tags:' . Term::load(2)->uuid(),
      ],
      'module' => [
        'node',
        'taxonomy',
        'user',
      ],
    ];
    $this->assertSame($expected, $view->calculateDependencies()->getDependencies());
  }

  /**
   * Tests the filter UI with multiple vocabularies.
   *
   * @group legacy
   */
  public function testFilterUIWithMultipleVocabularies(): void {
    $this->expectDeprecation("The 'vid' key in 'views.filter.taxonomy_index_tid' config schema is deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Update your view to use the 'vids' key instead. See https://www.drupal.org/node/3162414");
    $terms2 = $this->createVocabularyAndTerms('tags2');
    $node_type = $this->drupalCreateContentType(['type' => 'page']);
    // Create the tag field itself.
    $field_name = 'taxonomy_tags';
    $this->createEntityReferenceField('node', $node_type->id(), $field_name, NULL, 'taxonomy_term');
    // Create three nodes: 1 with a term from the first vocabulary,
    // and 1 with a term from the second vocabulary, and 1 with terms from both.
    $node0 = $this->drupalCreateNode([
      'type' => 'page',
      'taxonomy_tags' => $this->terms[0][0]->id(),
    ]);
    $node0->save();
    $node1 = $this->drupalCreateNode([
      'type' => 'page',
      'taxonomy_tags' => $terms2[0][0]->id(),
    ]);
    $node1->save();
    $node2 = $this->drupalCreateNode([
      'type' => 'page',
      'taxonomy_tags' => [$this->terms[0][0]->id(), $terms2[0][0]->id()],
    ]);
    $node2->save();
    // Edit the view to use the second vocabulary.
    $edit = [
      'options[vids][tags]' => TRUE,
      'options[vids][tags2]' => TRUE,
      'options[type]' => 'textfield',
    ];
    $this->drupalGet('admin/structure/views/nojs/handler-extra/test_filter_taxonomy_index_tid/default/filter/tid');
    $this->submitForm($edit, 'Apply');
    // Expose the filter.
    $this->drupalGet('admin/structure/views/nojs/handler/test_filter_taxonomy_index_tid/default/filter/tid');
    $this->submitForm([], 'Expose filter');

    $edit = [
      'options[operator]' => 'and',
      'options[value]' => '',
      'options[reduce_duplicates]' => TRUE,
    ];
    $this->submitForm($edit, 'Apply');
    $this->submitForm([], 'Save');
    // Check that the terms from both vocabularies are available in the UI.
    $this->drupalGet('test-filter-taxonomy-index-tid', ['query' => ['tid' => '']]);
    $xpath = $this->xpath('//div[@class="views-row"]//a');
    $this->assertCount(3, $xpath);
    // The nodes tagged with the term from the first vocabulary should be shown.
    $this->drupalGet('test-filter-taxonomy-index-tid', ['query' => ['tid' => "{$this->terms[0][0]->getName()}"]]);
    $xpath = $this->xpath('//div[@class="views-row"]//a');
    $this->assertCount(2, $xpath);
    $xpath = $this->xpath('//div[@class="views-row"]//a[@href=:href]', [
      ':href' => $node0->toUrl()->toString(),
    ]);
    $this->assertCount(1, $xpath);
    $xpath = $this->xpath('//div[@class="views-row"]//a[@href=:href]', [
      ':href' => $node2->toUrl()->toString(),
    ]);
    $this->assertCount(1, $xpath);
    $this->drupalGet('test-filter-taxonomy-index-tid', ['query' => ['tid' => "{$terms2[0][0]->getName()}"]]);
    $xpath = $this->xpath('//div[@class="views-row"]//a');
    $this->assertCount(2, $xpath);
    $xpath = $this->xpath('//div[@class="views-row"]//a[@href=:href]', [
      ':href' => $node1->toUrl()->toString(),
    ]);
    $this->assertCount(1, $xpath);
    $xpath = $this->xpath('//div[@class="views-row"]//a[@href=:href]', [
      ':href' => $node2->toUrl()->toString(),
    ]);
    $this->assertCount(1, $xpath);
    $this->drupalGet('test-filter-taxonomy-index-tid', ['query' => ['tid' => "{$this->terms[0][0]->getName()}, {$terms2[0][0]->getName()}"]]);
    $xpath = $this->xpath('//div[@class="views-row"]//a');
    $this->assertCount(1, $xpath);
    $xpath = $this->xpath('//div[@class="views-row"]//a[@href=:href]', [
      ':href' => $node2->toUrl()->toString(),
    ]);
    $this->assertCount(1, $xpath);
  }


  /**
   * Tests exposed taxonomy filters.
   *
   * @group legacy
   */
  public function testExposedFilter(): void {
    $node_type = $this->drupalCreateContentType(['type' => 'page']);

    // Create the tag field itself.
    $field_name = 'taxonomy_tags';
    $this->createEntityReferenceField('node', $node_type->id(), $field_name, NULL, 'taxonomy_term');

    // Create 4 nodes: 1 without a term, 2 with the same term, and 1 with a
    // different term.
    $node1 = $this->drupalCreateNode();
    $node2 = $this->drupalCreateNode([
      $field_name => [['target_id' => $this->terms[1][0]->id()]],
    ]);
    $node3 = $this->drupalCreateNode([
      $field_name => [['target_id' => $this->terms[1][0]->id()]],
    ]);
    $node4 = $this->drupalCreateNode([
      $field_name => [['target_id' => $this->terms[2][0]->id()]],
    ]);

    // Only the nodes with the selected term should be shown.
    $this->drupalGet('test-filter-taxonomy-index-tid');
    $this->assertSession()->pageTextNotContains($node1->getTitle());
    $this->assertSession()->linkByHrefNotExists($node1->toUrl()->toString());
    $xpath_node2_link = $this->assertSession()->buildXPathQuery('//div[@class="views-row"]//a[@href=:url and text()=:label]', [
      ':url' => $node2->toUrl()->toString(),
      ':label' => $node2->label(),
    ]);
    $this->assertSession()->elementsCount('xpath', $xpath_node2_link, 1);
    $xpath_node3_link = $this->assertSession()->buildXPathQuery('//div[@class="views-row"]//a[@href=:url and text()=:label]', [
      ':url' => $node3->toUrl()->toString(),
      ':label' => $node3->label(),
    ]);
    $this->assertSession()->elementsCount('xpath', $xpath_node3_link, 1);
    $this->assertSession()->pageTextNotContains($node4->getTitle());
    $this->assertSession()->linkByHrefNotExists($node4->toUrl()->toString());

    // Expose the filter.
    $this->drupalGet('admin/structure/views/nojs/handler/test_filter_taxonomy_index_tid/default/filter/tid');
    $this->submitForm([], 'Expose filter');
    // Set the operator to 'empty' and remove the default term ID.
    $this->submitForm([
      'options[operator]' => 'empty',
      'options[value][]' => [],
    ], 'Apply');
    // Save the view.
    $this->submitForm([], 'Save');

    // After switching to 'empty' operator, the node without a term should be
    // shown.
    $this->drupalGet('test-filter-taxonomy-index-tid');
    $xpath_node1_link = $this->assertSession()->buildXPathQuery('//div[@class="views-row"]//a[@href=:url and text()=:label]', [
      ':url' => $node1->toUrl()->toString(),
      ':label' => $node1->label(),
    ]);
    $this->assertSession()->elementsCount('xpath', $xpath_node1_link, 1);
    $this->assertSession()->pageTextNotContains($node2->getTitle());
    $this->assertSession()->linkByHrefNotExists($node2->toUrl()->toString());
    $this->assertSession()->pageTextNotContains($node3->getTitle());
    $this->assertSession()->linkByHrefNotExists($node3->toUrl()->toString());
    $this->assertSession()->pageTextNotContains($node4->getTitle());
    $this->assertSession()->linkByHrefNotExists($node4->toUrl()->toString());

    // Set the operator to 'not empty'.
    $this->drupalGet('admin/structure/views/nojs/handler/test_filter_taxonomy_index_tid/default/filter/tid');
    $this->submitForm(['options[operator]' => 'not empty'], 'Apply');
    // Save the view.
    $this->submitForm([], 'Save');

    // After switching to 'not empty' operator, all nodes with terms should be
    // shown.
    $this->drupalGet('test-filter-taxonomy-index-tid');
    $this->assertSession()->pageTextNotContains($node1->getTitle());
    $this->assertSession()->linkByHrefNotExists($node1->toUrl()->toString());
    $xpath_node2_link = $this->assertSession()->buildXPathQuery('//div[@class="views-row"]//a[@href=:url and text()=:label]', [
      ':url' => $node2->toUrl()->toString(),
      ':label' => $node2->label(),
    ]);
    $this->assertSession()->elementsCount('xpath', $xpath_node2_link, 1);
    $xpath_node3_link = $this->assertSession()->buildXPathQuery('//div[@class="views-row"]//a[@href=:url and text()=:label]', [
      ':url' => $node3->toUrl()->toString(),
      ':label' => $node3->label(),
    ]);
    $this->assertSession()->elementsCount('xpath', $xpath_node3_link, 1);
    $xpath_node4_link = $this->assertSession()->buildXPathQuery('//div[@class="views-row"]//a[@href=:url and text()=:label]', [
      ':url' => $node4->toUrl()->toString(),
      ':label' => $node4->label(),
    ]);
    $this->assertSession()->elementsCount('xpath', $xpath_node4_link, 1);

    // Select 'Term ID' as the field to be displayed.
    $edit = ['name[taxonomy_term_field_data.tid]' => TRUE];
    $this->drupalGet('admin/structure/views/nojs/add-handler/test_taxonomy_term_name/default/field');
    $this->submitForm($edit, 'Add and configure fields');
    // Select 'Term' and 'Vocabulary' as filters.
    $edit = [
      'name[taxonomy_term_field_data.tid]' => TRUE,
      'name[taxonomy_term_field_data.vid]' => TRUE,
    ];
    $this->drupalGet('admin/structure/views/nojs/add-handler/test_taxonomy_term_name/default/filter');
    $this->submitForm($edit, 'Add and configure filter criteria');
    // Select 'Empty Vocabulary' and 'Autocomplete' from the list of options.
    $edit = [
      'options[vids][empty_vocabulary]' => TRUE,
      'options[type]' => 'textfield',
    ];
    $this->drupalGet('admin/structure/views/nojs/handler-extra/test_taxonomy_term_name/default/filter/tid');
    $this->submitForm($edit, 'Apply and continue');
    // Expose the filter.
    $edit = ['options[expose_button][checkbox][checkbox]' => TRUE];
    $this->drupalGet('admin/structure/views/nojs/handler/test_taxonomy_term_name/default/filter/tid');
    $this->submitForm($edit, 'Expose filter');
    $this->drupalGet('admin/structure/views/nojs/handler/test_taxonomy_term_name/default/filter/tid');
    $this->submitForm($edit, 'Apply');
    // Filter 'Taxonomy terms' belonging to 'Empty Vocabulary'.
    $edit = ['options[value][empty_vocabulary]' => TRUE];
    $this->drupalGet('admin/structure/views/nojs/handler/test_taxonomy_term_name/default/filter/vid');
    $this->submitForm($edit, 'Apply');
    $this->drupalGet('admin/structure/views/view/test_taxonomy_term_name/edit/default');
    $this->submitForm([], 'Save');
    $this->submitForm([], 'Update preview');
    $this->assertSession()->pageTextNotContains($node1->getTitle());
    $this->assertSession()->linkByHrefNotExists($node1->toUrl()->toString());
    $this->assertSession()->pageTextNotContains($node2->getTitle());
    $this->assertSession()->linkByHrefNotExists($node2->toUrl()->toString());
    $this->assertSession()->pageTextNotContains($node3->getTitle());
    $this->assertSession()->linkByHrefNotExists($node3->toUrl()->toString());
    $this->assertSession()->pageTextNotContains($node4->getTitle());
    $this->assertSession()->linkByHrefNotExists($node4->toUrl()->toString());
    $this->assertSession()->elementNotExists('xpath', "//div[@class='views-row']");
  }

  /**
   * Tests exposed grouped taxonomy filters.
   *
   * @group legacy
   */
  public function testExposedGroupedFilter(): void {
    // Create a content type with a taxonomy field.
    $this->drupalCreateContentType(['type' => 'article']);
    $field_name = 'field_views_testing_tags';
    $this->createEntityReferenceField('node', 'article', $field_name, NULL, 'taxonomy_term');

    $nodes = [];
    for ($i = 0; $i < 3; $i++) {
      $node = [];
      $node['type'] = 'article';
      $node['field_views_testing_tags'][0]['target_id'] = $this->terms[$i][0]->id();
      $nodes[] = $this->drupalCreateNode($node);
    }

    $this->drupalGet('admin/structure/views/nojs/handler/test_taxonomy_exposed_grouped_filter/page_1/filter/field_views_testing_tags_target_id');
    $edit = [
      'options[group_info][group_items][1][value][]' => [$this->terms[0][0]->id(), $this->terms[1][0]->id()],
      'options[group_info][group_items][2][value][]' => [$this->terms[1][0]->id(), $this->terms[2][0]->id()],
      'options[group_info][group_items][3][value][]' => [$this->terms[2][0]->id(), $this->terms[0][0]->id()],
    ];
    $this->submitForm($edit, 'Apply');
    $this->submitForm([], 'Save');

    // Visit the view's page URL and validate the results.
    $this->drupalGet('test-taxonomy-exposed-grouped-filter');
    $this->submitForm(['field_views_testing_tags_target_id' => 1], 'Apply');
    $this->assertSession()->pageTextContains($nodes[0]->getTitle());
    $this->assertSession()->pageTextContains($nodes[1]->getTitle());
    $this->assertSession()->pageTextNotContains($nodes[2]->getTitle());

    $this->submitForm(['field_views_testing_tags_target_id' => 2], 'Apply');
    $this->assertSession()->pageTextContains($nodes[1]->getTitle());
    $this->assertSession()->pageTextContains($nodes[2]->getTitle());
    $this->assertSession()->pageTextNotContains($nodes[0]->getTitle());

    $this->submitForm(['field_views_testing_tags_target_id' => 3], 'Apply');
    $this->assertSession()->pageTextContains($nodes[0]->getTitle());
    $this->assertSession()->pageTextContains($nodes[2]->getTitle());
    $this->assertSession()->pageTextNotContains($nodes[1]->getTitle());
  }

  /**
   * Tests that an exposed taxonomy filter doesn't show unpublished terms.
   *
   * @group legacy
   */
  public function testExposedUnpublishedFilterOptions(): void {
    $this->terms[1][0]->setUnpublished()->save();
    // Expose the filter.
    $this->drupalGet('admin/structure/views/nojs/handler/test_filter_taxonomy_index_tid/default/filter/tid');
    $this->submitForm([], 'Expose filter');
    $edit = ['options[expose_button][checkbox][checkbox]' => TRUE];
    $this->submitForm($edit, 'Apply');
    $this->submitForm([], 'Save');
    // Make sure the unpublished term is shown to the admin user.
    $this->drupalGet('test-filter-taxonomy-index-tid');
    $this->assertNotEmpty($this->cssSelect('option[value="' . $this->terms[0][0]->id() . '"]'));
    $this->assertNotEmpty($this->cssSelect('option[value="' . $this->terms[1][0]->id() . '"]'));
    $this->drupalLogout();
    $this->drupalGet('test-filter-taxonomy-index-tid');
    // Make sure the unpublished term isn't shown to the anonymous user.
    $this->assertNotEmpty($this->cssSelect('option[value="' . $this->terms[0][0]->id() . '"]'));
    $this->assertEmpty($this->cssSelect('option[value="' . $this->terms[1][0]->id() . '"]'));

    // Tests that the term also isn't shown when not showing hierarchy.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'options[hierarchy]' => FALSE,
    ];
    $this->drupalGet('admin/structure/views/nojs/handler-extra/test_filter_taxonomy_index_tid/default/filter/tid');
    $this->submitForm($edit, 'Apply');
    $this->submitForm([], 'Save');
    $this->drupalGet('test-filter-taxonomy-index-tid');
    $this->assertNotEmpty($this->cssSelect('option[value="' . $this->terms[0][0]->id() . '"]'));
    $this->assertNotEmpty($this->cssSelect('option[value="' . $this->terms[1][0]->id() . '"]'));
    $this->drupalLogout();
    $this->drupalGet('test-filter-taxonomy-index-tid');
    // Make sure the unpublished term isn't shown to the anonymous user.
    $this->assertNotEmpty($this->cssSelect('option[value="' . $this->terms[0][0]->id() . '"]'));
    $this->assertEmpty($this->cssSelect('option[value="' . $this->terms[1][0]->id() . '"]'));
  }

  /**
   * Tests using the TaxonomyIndexTid in a filter group.
   */
  public function testFilterGrouping(): void {
    $node_type = $this->drupalCreateContentType(['type' => 'page']);

    // Create the tag field itself.
    $field_name = 'taxonomy_tags';
    $this->createEntityReferenceField('node', $node_type->id(), $field_name, NULL, 'taxonomy_term');

    // Create the other tag field itself.
    $field_name2 = 'taxonomy_other_tags';
    $this->createEntityReferenceField('node', $node_type->id(), $field_name2, NULL, 'taxonomy_term');

    // Create 5 nodes: 1 node without any tagging, 2 nodes tagged with 1 term,
    // 1 node with 2 tagged terms and 1 with other tags term.
    $node_no_term = $this->drupalCreateNode();
    $node_with_term_1_0 = $this->drupalCreateNode([
      $field_name => [['target_id' => $this->terms[1][0]->id()]],
    ]);
    $node_with_terms_1_0_and_1_1 = $this->drupalCreateNode([
      $field_name => [
        ['target_id' => $this->terms[1][0]->id()],
        ['target_id' => $this->terms[1][1]->id()],
      ],
    ]);
    $node_with_term_2_0 = $this->drupalCreateNode([
      $field_name => [['target_id' => $this->terms[2][0]->id()]],
    ]);

    $node_with_term_3_0 = $this->drupalCreateNode([
      $field_name2 => [['target_id' => $this->terms[3][0]->id()]],
    ]);

    // Create two groups. The first group contains the published filter and set
    // up the second group as an 'OR' group. The first subgroup of the second
    // filter group will vary as follows:
    // - multiple values vs single value
    // - not vs or operator values
    $view = View::load('test_filter_taxonomy_index_tid');
    $display =& $view->getDisplay('default');
    // Case 1:
    // - filter "tid" with multiple terms as "is none of"
    // - filter "tid_2" with a single term as "is one of"
    $display['display_options']['filters']['tid']['value'][0] = $this->terms[1][0]->id();
    $display['display_options']['filters']['tid']['value'][1] = $this->terms[1][1]->id();
    $display['display_options']['filters']['tid']['operator'] = 'not';
    $display['display_options']['filters']['tid']['group'] = 2;
    $display['display_options']['filters']['tid_2'] = $display['display_options']['filters']['tid'];
    $display['display_options']['filters']['tid_2']['id'] = 'tid_2';
    $display['display_options']['filters']['tid_2']['value'][0] = $this->terms[2][0]->id();
    $display['display_options']['filters']['tid_2']['operator'] = 'or';
    $display['display_options']['filters']['tid_2']['group'] = 2;
    $display['display_options']['filter_groups'] = [
      'operator' => 'AND',
      'groups' => [
        1 => 'AND',
        2 => 'OR',
      ],
    ];
    $view->save();

    $this->drupalGet('test-filter-taxonomy-index-tid');
    // We expect no nodes tagged with term 1.0 or 1.1. The node tagged with
    // term 2.0 and the untagged node will be shown.
    $this->assertSession()->pageTextNotContains($node_with_term_1_0->label());
    $this->assertSession()->pageTextNotContains($node_with_terms_1_0_and_1_1->label());
    $this->assertSession()->pageTextContainsOnce($node_with_term_2_0->label());
    $this->assertSession()->pageTextContainsOnce($node_no_term->label());

    // Case 2:
    // - filter "tid" with multiple terms as "is one of"
    // - filter "tid_2" with a single term as "is one of"
    $view = View::load('test_filter_taxonomy_index_tid');
    $display =& $view->getDisplay('default');
    $display['display_options']['filters']['tid']['value'][0] = $this->terms[1][0]->id();
    $display['display_options']['filters']['tid']['value'][1] = $this->terms[1][1]->id();
    $display['display_options']['filters']['tid']['operator'] = 'or';
    $display['display_options']['filters']['tid']['group'] = 2;
    $display['display_options']['filters']['tid_2'] = $display['display_options']['filters']['tid'];
    $display['display_options']['filters']['tid_2']['id'] = 'tid_2';
    $display['display_options']['filters']['tid_2']['value'][0] = $this->terms[2][0]->id();
    $display['display_options']['filters']['tid_2']['operator'] = 'or';
    $display['display_options']['filters']['tid_2']['group'] = 2;
    $view->save();

    $this->drupalGet('test-filter-taxonomy-index-tid');
    // We expect all the tagged nodes but not the untagged node.
    $this->assertSession()->pageTextContainsOnce($node_with_term_1_0->label());
    // The view does not have DISTINCT query enabled, the node tagged with
    // both 1.0 and 1.1 will appear twice.
    $this->assertSession()->pageTextMatchesCount(2, "/{$node_with_terms_1_0_and_1_1->label()}/");
    $this->assertSession()->pageTextContainsOnce($node_with_term_2_0->label());
    $this->assertSession()->pageTextNotContains($node_no_term->label());

    // Case 3:
    // - filter "tid" with a single term as "is none of"
    // - filter "tid_2" with a single term as "is one of"
    $view = View::load('test_filter_taxonomy_index_tid');
    $display =& $view->getDisplay('default');
    $display['display_options']['filters']['tid']['value'] = [];
    $display['display_options']['filters']['tid']['value'][0] = $this->terms[1][0]->id();
    $display['display_options']['filters']['tid']['operator'] = 'not';
    $display['display_options']['filters']['tid']['group'] = 2;
    $display['display_options']['filters']['tid_2'] = $display['display_options']['filters']['tid'];
    $display['display_options']['filters']['tid_2']['id'] = 'tid_2';
    $display['display_options']['filters']['tid_2']['value'][0] = $this->terms[2][0]->id();
    $display['display_options']['filters']['tid_2']['operator'] = 'or';
    $display['display_options']['filters']['tid_2']['group'] = 2;
    $view->save();

    $this->drupalGet('test-filter-taxonomy-index-tid');
    // We expect none of the nodes tagged with term 1.0. The node tagged with
    // term 2.0 and the untagged node should be shown.
    $this->assertSession()->pageTextNotContains($node_with_term_1_0->label());
    $this->assertSession()->pageTextNotContains($node_with_terms_1_0_and_1_1->label());
    $this->assertSession()->pageTextContainsOnce($node_with_term_2_0->label());
    $this->assertSession()->pageTextContainsOnce($node_no_term->label());

    // Case 4:
    // - filter "tid" with a single term as "is one of"
    // - filter "tid_2" with a single term as "is one of"
    $view = View::load('test_filter_taxonomy_index_tid');
    $display =& $view->getDisplay('default');
    $display['display_options']['filters']['tid']['value'] = [];
    $display['display_options']['filters']['tid']['value'][0] = $this->terms[1][0]->id();
    $display['display_options']['filters']['tid']['operator'] = 'or';
    $display['display_options']['filters']['tid']['group'] = 2;
    $display['display_options']['filters']['tid_2'] = $display['display_options']['filters']['tid'];
    $display['display_options']['filters']['tid_2']['id'] = 'tid_2';
    $display['display_options']['filters']['tid_2']['value'][0] = $this->terms[2][0]->id();
    $view->save();

    $this->drupalGet('test-filter-taxonomy-index-tid');
    // We expect all the tagged nodes to be shown but not the untagged node.
    $this->assertSession()->pageTextContainsOnce($node_with_term_1_0->label());
    $this->assertSession()->pageTextContainsOnce($node_with_terms_1_0_and_1_1->label());
    $this->assertSession()->pageTextContainsOnce($node_with_term_2_0->label());
    $this->assertSession()->pageTextNotContains($node_no_term->label());

    // Different fields/taxonomies filters/values.
    // Case 5: OR
    // - filter "tid" with terms from tags as "is one of"
    // - filter "taxonomy_other_tags_target_id" with term from other tags
    // as "is one of".
    $view = View::load('test_filter_taxonomy_index_tid');
    $display = &$view->getDisplay('default');
    $display['display_options']['filters']['tid']['value'][0] = $this->terms[1][0]->id();
    $display['display_options']['filters']['tid']['value'][1] = $this->terms[1][1]->id();
    $display['display_options']['filters']['tid']['operator'] = 'or';
    $display['display_options']['filters']['tid']['group'] = 2;
    $display['display_options']['filters']['taxonomy_other_tags_target_id'] = $display['display_options']['filters']['tid'];
    $display['display_options']['filters']['taxonomy_other_tags_target_id']['id'] = 'taxonomy_other_tags_target_id';
    $display['display_options']['filters']['taxonomy_other_tags_target_id']['value'][0] = $this->terms[3][0]->id();
    $display['display_options']['filters']['taxonomy_other_tags_target_id']['operator'] = 'or';
    $display['display_options']['filters']['taxonomy_other_tags_target_id']['group'] = 2;
    $display['display_options']['filters']['taxonomy_other_tags_target_id']['table'] = 'node__taxonomy_other_tags';
    $display['display_options']['filters']['taxonomy_other_tags_target_id']['field'] = 'taxonomy_other_tags_target_id';
    unset($display['display_options']['filters']['tid_2']);
    $display['display_options']['filter_groups'] = [
      'operator' => 'AND',
      'groups' => [
        1 => 'AND',
        2 => 'OR',
      ],
    ];
    $view->save();

    $this->drupalGet('test-filter-taxonomy-index-tid');
    // We expect no nodes tagged with term 1.0 or 1.1. The node tagged with
    // term 3.0 and the untagged node will be shown.
    $this->assertSession()->pageTextContainsOnce($node_with_term_1_0->label());
    // The view does not have DISTINCT query enabled, the node tagged with
    // both 1.0 and 1.1 will appear twice.
    $this->assertSession()->pageTextMatchesCount(2, "/{$node_with_terms_1_0_and_1_1->label()}/");
    $this->assertSession()->pageTextContainsOnce($node_with_term_3_0->label());
    $this->assertSession()->pageTextNotContains($node_with_term_2_0->label());
    $this->assertSession()->pageTextNotContains($node_no_term->label());
  }

}
