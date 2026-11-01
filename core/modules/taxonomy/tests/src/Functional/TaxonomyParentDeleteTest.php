<?php

namespace Drupal\Tests\taxonomy\Functional;

/**
 * Ensure that the parent delete works properly.
 *
 * @group taxonomy
 */
class TaxonomyParentDeleteTest extends TaxonomyTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['taxonomy'];

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser([
      'administer taxonomy',
      'bypass node access',
    ]));
    $this->vocabulary = $this->createVocabulary();
  }

  /**
   * Tests term indentation.
   */
  public function testTermIndentation() {
    $assert = $this->assertSession();
    // Create three taxonomy terms.
    $term1 = $this->createTerm($this->vocabulary);
    $term2 = $this->createTerm($this->vocabulary);

    // Get the taxonomy storage.
    $taxonomy_storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');

    // Indent the second term under the first one.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabulary->get('vid') . '/overview');
    $hidden_edit = [
      'terms[tid:' . $term2->id() . ':0][term][tid]' => 2,
      'terms[tid:' . $term2->id() . ':0][term][parent]' => 1,
      'terms[tid:' . $term2->id() . ':0][term][depth]' => 1,
    ];
    // Because we can't post hidden form elements, we have to change them in
    // code here, and then submit.
    foreach ($hidden_edit as $field => $value) {
      $node = $assert->hiddenFieldExists($field);
      $node->setValue($value);
    }
    $edit = [
      'terms[tid:' . $term2->id() . ':0][weight]' => 1,
    ];
    // Submit the edited form and check for HTML indentation element presence.
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseMatches('|<div class="js-indentation indentation">&nbsp;</div>|');

    // Check explicitly that term 2's parent is term 1.
    $parents = $taxonomy_storage->loadParents($term2->id());
    $this->assertEquals(1, key($parents), 'Term 1 is the term 2\'s parent');

    // Check the deletion confirmation message with list of child.
    $this->drupalGet('taxonomy/term/' . $term1->id() . '/delete');
    $this->assertSession()->pageTextContains('Deleting taxonomy term ' . $term1->getName() . ' will also delete its descendant ' . $term2->getName() . '. This action cannot be undone.');

    // Submit the delete form and check for status message have child list.
    $this->submitForm([], 'Delete');
    $this->assertSession()->pageTextContains('The taxonomy terms ' . $term1->getName() . ' and ' . $term2->getName() . ' have been deleted.');
  }

}
