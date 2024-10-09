<?php

declare(strict_types=1);

namespace Drupal\Tests\taxonomy\Functional;

use Drupal\taxonomy\VocabularyInterface;

/**
 * Ensure that the term overview page filtering works properly.
 *
 * @group taxonomy
 */
class TaxonomyTermFilterTest extends TaxonomyTestBase {

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
  protected VocabularyInterface $vocabulary;

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

  public function testTaxonomyTermOverviewFilterWithoutHierarchy(): void {

    // Create terms.
    $firstTerm = $this->createTerm($this->vocabulary, [
      'name' => 'First term',
    ]);
    $secondTerm = $this->createTerm($this->vocabulary, [
      'name' => 'Second term',
    ]);
    $thirdTerm = $this->createTerm($this->vocabulary, [
      'name' => 'Third term',
    ]);

    // Get vocabulary's term overview page.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/overview');

    // Check that all terms are displayed when no filter is applied.
    $this->assertSession()->pageTextContains($firstTerm->label());
    $this->assertSession()->pageTextContains($secondTerm->label());
    $this->assertSession()->pageTextContains($thirdTerm->label());

    $this->submitForm([
      'filter' => 'con',
    ], 'Filter');

    // Check that only second term is displayed when a proper filter is applied.
    $this->assertSession()->pageTextNotContains($firstTerm->label());
    $this->assertSession()->pageTextContains($secondTerm->label());
    $this->assertSession()->pageTextNotContains($thirdTerm->label());

  }

  public function testTaxonomyTermOverviewFilterWithHierarchy(): void {

    // Create terms.
    $firstTerm = $this->createTerm($this->vocabulary, [
      'name' => 'Term 1',
      'parent' => 0,
    ]);
    $secondTerm = $this->createTerm($this->vocabulary, [
      'name' => 'Term 2',
      'parent' => 0,
    ]);
    $secondTermChild = $this->createTerm($this->vocabulary, [
      'name' => 'Term 2.1',
      'parent' => $secondTerm->id(),
    ]);
    $secondTermGrandChild = $this->createTerm($this->vocabulary, [
      'name' => 'Term 2.1.1',
      'parent' => $secondTermChild->id(),
    ]);
    $thirdTerm = $this->createTerm($this->vocabulary, [
      'name' => 'Term 3',
      'parent' => 0,
    ]);

    // Get vocabulary's term overview page.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/overview');

    // Check that all terms are displayed when no filter is applied.
    $this->assertSession()->pageTextContains($firstTerm->label());
    $this->assertSession()->pageTextContains($secondTerm->label());
    $this->assertSession()->pageTextContains($secondTermChild->label());
    $this->assertSession()->pageTextContains($secondTermGrandChild->label());
    $this->assertSession()->pageTextContains($thirdTerm->label());

    // Check that an only root matching term is displayed alone.
    $this->submitForm([
      'filter' => 'Term 1',
    ], 'Filter');
    $this->assertSession()->pageTextContains($firstTerm->label());
    $this->assertSession()->pageTextNotContains($secondTerm->label());
    $this->assertSession()->pageTextNotContains($secondTermChild->label());
    $this->assertSession()->pageTextNotContains($secondTermGrandChild->label());
    $this->assertSession()->pageTextNotContains($thirdTerm->label());

    // Check that a deep non-root matching term is displayed with all its parents.
    $this->submitForm([
      'filter' => 'Term 2.1.1',
    ], 'Filter');
    $this->assertSession()->pageTextNotContains($firstTerm->label());
    $this->assertSession()->pageTextContains($secondTerm->label());
    $this->assertSession()->pageTextContains($secondTermChild->label());
    $this->assertSession()->pageTextContains($secondTermGrandChild->label());
    $this->assertSession()->pageTextNotContains($thirdTerm->label());

  }

}
