<?php

declare(strict_types=1);

namespace Drupal\Tests\taxonomy\Functional;

use Drupal\Core\Database\Database;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the hook implementations that maintain the taxonomy index.
 */
#[Group('taxonomy')]
#[RunTestsInSeparateProcesses]
class TermIndexTest extends TaxonomyTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Vocabulary for testing.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * Name of the taxonomy term reference field.
   *
   * @var string
   */
  protected $fieldName1;

  /**
   * Name of the taxonomy term reference field.
   *
   * @var string
   */
  protected $fieldName2;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an administrative user.
    $this->drupalLogin($this->drupalCreateUser([
      'administer taxonomy',
      'bypass node access',
    ]));

    // Create a vocabulary and add two term reference fields to article nodes.
    $this->vocabulary = $this->createVocabulary();

    $this->fieldName1 = $this->randomMachineName();
    $handler_settings = [
      'target_bundles' => [
        $this->vocabulary->id() => $this->vocabulary->id(),
      ],
      'auto_create' => TRUE,
    ];
    $this->createEntityReferenceField('node', 'article', $this->fieldName1, '', 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getFormDisplay('node', 'article')
      ->setComponent($this->fieldName1, [
        'type' => 'options_select',
      ])
      ->save();
    $display_repository->getViewDisplay('node', 'article')
      ->setComponent($this->fieldName1, [
        'type' => 'entity_reference_label',
      ])
      ->save();

    $this->fieldName2 = $this->randomMachineName();
    $this->createEntityReferenceField('node', 'article', $this->fieldName2, '', 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getFormDisplay('node', 'article')
      ->setComponent($this->fieldName2, [
        'type' => 'options_select',
      ])
      ->save();
    $display_repository->getViewDisplay('node', 'article')
      ->setComponent($this->fieldName2, [
        'type' => 'entity_reference_label',
      ])
      ->save();
  }

  /**
   * Tests that the taxonomy index is maintained properly.
   */
  public function testTaxonomyIndex(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    // Create terms in the vocabulary.
    $term_1 = $this->createTerm($this->vocabulary);
    $term_2 = $this->createTerm($this->vocabulary);

    // Post an article.
    $edit = [];
    $edit['title[0][value]'] = $this->randomMachineName();
    $edit['body[0][value]'] = $this->randomMachineName();
    $edit["{$this->fieldName1}[]"] = $term_1->id();
    $edit["{$this->fieldName2}[]"] = $term_1->id();
    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');

    // Check that the term is indexed, and only once.
    $node = $this->drupalGetNodeByTitle($edit['title[0][value]']);
    $connection = Database::getConnection();
    $index_count = $connection->select('taxonomy_index')
      ->condition('nid', $node->id())
      ->condition('tid', $term_1->id())
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(1, $index_count, 'Term 1 is indexed once.');

    // Update the article to change one term.
    $edit["{$this->fieldName1}[]"] = $term_2->id();
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->submitForm($edit, 'Save');

    // Check that both terms are indexed.
    $index_count = $connection->select('taxonomy_index')
      ->condition('nid', $node->id())
      ->condition('tid', $term_1->id())
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(1, $index_count, 'Term 1 is indexed.');
    $index_count = $connection->select('taxonomy_index')
      ->condition('nid', $node->id())
      ->condition('tid', $term_2->id())
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(1, $index_count, 'Term 2 is indexed.');

    // Update the article to change another term.
    $edit["{$this->fieldName2}[]"] = $term_2->id();
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->submitForm($edit, 'Save');

    // Check that only one term is indexed.
    $index_count = $connection->select('taxonomy_index')
      ->condition('nid', $node->id())
      ->condition('tid', $term_1->id())
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(0, $index_count, 'Term 1 is not indexed.');
    $index_count = $connection->select('taxonomy_index')
      ->condition('nid', $node->id())
      ->condition('tid', $term_2->id())
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(1, $index_count, 'Term 2 is indexed once.');

    // Redo the above tests without interface.
    $node = $node_storage->load($node->id());
    $node->title = $this->randomMachineName();

    // Update the article with no term changed.
    $node->save();

    // Check that the index was not changed.
    $index_count = $connection->select('taxonomy_index')
      ->condition('nid', $node->id())
      ->condition('tid', $term_1->id())
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(0, $index_count, 'Term 1 is not indexed.');
    $index_count = $connection->select('taxonomy_index')
      ->condition('nid', $node->id())
      ->condition('tid', $term_2->id())
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(1, $index_count, 'Term 2 is indexed once.');

    // Update the article to change one term.
    $node->{$this->fieldName1} = [['target_id' => $term_1->id()]];
    $node->save();

    // Check that both terms are indexed.
    $index_count = $connection->select('taxonomy_index')
      ->condition('nid', $node->id())
      ->condition('tid', $term_1->id())
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(1, $index_count, 'Term 1 is indexed.');
    $index_count = $connection->select('taxonomy_index')
      ->condition('nid', $node->id())
      ->condition('tid', $term_2->id())
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(1, $index_count, 'Term 2 is indexed.');

    // Update the article to change another term.
    $node->{$this->fieldName2} = [['target_id' => $term_1->id()]];
    $node->save();

    // Check that only one term is indexed.
    $index_count = $connection->select('taxonomy_index')
      ->condition('nid', $node->id())
      ->condition('tid', $term_1->id())
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(1, $index_count, 'Term 1 is indexed once.');
    $index_count = $connection->select('taxonomy_index')
      ->condition('nid', $node->id())
      ->condition('tid', $term_2->id())
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(0, $index_count, 'Term 2 is not indexed.');
  }

  /**
   * Tests that there is a link to the parent term on the child term page.
   */
  public function testTaxonomyTermHierarchyBreadcrumbs(): void {
    // Create two taxonomy terms and set term2 as the parent of term1.
    $term1 = $this->createTerm($this->vocabulary);
    $term2 = $this->createTerm($this->vocabulary);
    $term1->parent = [$term2->id()];
    $term1->save();

    // Verify that the page breadcrumbs include a link to the parent term.
    $this->drupalGet('taxonomy/term/' . $term1->id());
    // Breadcrumbs are not rendered with a language, prevent the term
    // language from being added to the options.
    // Check that parent term link is displayed when viewing the node.
    $this->assertSession()->responseContains(Link::fromTextAndUrl($term2->getName(), $term2->toUrl('canonical', ['language' => NULL]))->toString());
  }

  /**
   * Helper function.
   *
   * @param int $nid
   *   The node ID.
   * @param int $tid
   *   The taxonomy term ID.
   * @param array $expected_numbers
   *   The expected number of entries keyed by the langcode.
   *
   * @return array
   *   An array of results for each language code, including the expected number and actual count.
   */
  protected function checkNumberOfEntriesPerLanguage(int $nid, int $tid, array $expected_numbers): array {
    $connection = Database::getConnection();
    // Initialize an array to store the results.
    $results = [];

    foreach ($expected_numbers as $langcode => $expected_number) {
      $index_count = $connection->query('SELECT COUNT(*) FROM {taxonomy_index} WHERE nid = :nid AND tid = :tid AND langcode = :langcode', [
        ':nid' => $nid,
        ':tid' => $tid,
        ':langcode' => $langcode,
      ])->fetchField();
      $this->assertEquals($expected_number, $index_count, 'The actual number of entry ' . $index_count . ' for langcode ' . $langcode . ' is matching the expected number of ' . $expected_number);

      // Store the result in the array.
      $results[$langcode] = [
        'expected' => $expected_number,
        'actual' => $index_count,
      ];
    }

    // Return the results array.
    return $results;
  }

  /**
   * Tests that the taxonomy index is maintained properly.
   */
  public function testTaxonomyIndexMultilingual(): void {
    \Drupal::service('module_installer')->install(['language', 'content_translation']);
    // Create an Urdu language for translations.
    ConfigurableLanguage::createFromLangcode('ur')->save();
    // Create a French language for translations.
    ConfigurableLanguage::createFromLangcode('fr')->save();
    // Enable translation for the article content type and ensure the change is
    // picked up.
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    // Create term in the vocabulary.
    $term_1 = $this->createTerm($this->vocabulary);

    // Create an article.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'langcode' => 'en',
      $this->fieldName1 => [
        $term_1->id(),
      ],
    ]);
    $node->save();
    $nid = $node->id();

    // Reload the node to be sure every modification had been taken into
    // account.
    $node = $node_storage->load($nid);
    $node->addTranslation('ur', [
      'title' => $this->randomMachineName(),
      $this->fieldName1 => [
        $term_1->id(),
      ],
    ]);
    $node->save();

    $node = $node_storage->load($nid);
    $node->addTranslation('fr', [
      'title' => $this->randomMachineName(),
    ]);
    $node->save();

    // Check taxonomy index entries after translation creation.
    $this->checkNumberOfEntriesPerLanguage((int) $nid, (int) $term_1->id(), [
      'en' => 1,
      'ur' => 1,
      'fr' => 0,
    ]);

    // Add a reference to the term in French.
    $node = $node_storage->load($nid);
    $translation = $node->getTranslation('fr');
    $translation->set($this->fieldName1, [
      $term_1->id(),
    ]);
    $translation->save();
    $this->checkNumberOfEntriesPerLanguage((int) $nid, (int) $term_1->id(), [
      'en' => 1,
      'ur' => 1,
      'fr' => 1,
    ]);

    // Delete the Urdu translation.
    $node = $node_storage->load($nid);
    $node->removeTranslation('ur');
    $node->save();
    $this->checkNumberOfEntriesPerLanguage((int) $nid, (int) $term_1->id(), [
      'en' => 1,
      'ur' => 0,
      'fr' => 1,
    ]);

    // Delete the whole node.
    $node = $node_storage->load($nid);
    $node->delete();
    $this->checkNumberOfEntriesPerLanguage((int) $nid, (int) $term_1->id(), [
      'en' => 0,
      'ur' => 0,
      'fr' => 0,
    ]);
  }

}
