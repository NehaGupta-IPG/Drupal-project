<?php

declare(strict_types=1);

namespace Drupal\Tests\taxonomy\Kernel;

use Drupal\Core\Url;
use Drupal\Tests\system\Functional\Menu\AssertBreadcrumbTrait;
use Drupal\Tests\taxonomy\Functional\TaxonomyTranslationTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for proper breadcrumb translation.
 */
#[Group('taxonomy')]
#[RunTestsInSeparateProcesses]
class TermTranslationTest extends TaxonomyTestBase {

  use AssertBreadcrumbTrait;
  use TaxonomyTranslationTestTrait;

  /**
   * Term to translated term mapping.
   *
   * @var array
   */
  protected $termTranslationMap = [
    'one' => 'translatedOne',
    'two' => 'translatedTwo',
    'three' => 'translatedThree',
  ];

  /**
   * Created terms.
   *
   * @var \Drupal\taxonomy\Entity\Term[]
   */
  protected $terms = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['taxonomy', 'language', 'content_translation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('system.site')->set('name', 'Drupal')->save();
    $this->installConfig(['language']);
    $this->setupLanguages();
    $this->vocabulary = $this->createVocabulary();
    $this->enableTranslation();
    $this->setUpTerms();
    $this->setUpTermReferenceField();
  }

  /**
   * Tests translated breadcrumbs.
   */
  public function testTranslatedBreadcrumbs(): void {
    $this->setUpCurrentUser(permissions: ['access content']);

    // Ensure non-translated breadcrumb is correct.
    $breadcrumb = [Url::fromRoute('<front>')->toString() => 'Home'];
    foreach ($this->terms as $term) {
      $breadcrumb[$term->toUrl()->toString()] = $term->label();
    }
    // The last item will not be in the breadcrumb.
    array_pop($breadcrumb);

    // Check the breadcrumb on the leaf term page.
    $term = $this->getLeafTerm();
    $this->assertBreadcrumb($term->toUrl(), $breadcrumb, $term->label());

    $languages = \Drupal::languageManager()->getLanguages();

    // Construct the expected translated breadcrumb.
    $breadcrumb = [Url::fromRoute('<front>', [], ['language' => $languages[$this->translateToLangcode]])->toString() => 'Home'];
    foreach ($this->terms as $term) {
      $translated = $term->getTranslation($this->translateToLangcode);
      $url = $translated->toUrl('canonical', ['language' => $languages[$this->translateToLangcode]])->toString();
      $breadcrumb[$url] = $translated->label();
    }
    array_pop($breadcrumb);

    // Check for the translated breadcrumb on the translated leaf term page.
    $term = $this->getLeafTerm();
    $translated = $term->getTranslation($this->translateToLangcode);
    $this->assertBreadcrumb($translated->toUrl('canonical', ['language' => $languages[$this->translateToLangcode]]), $breadcrumb, $translated->label());

  }

  /**
   * Tests translation of terms are showed in the node.
   */
  public function testTermsTranslation(): void {

    // Set the display of the term reference field on the article content type
    // to "Check boxes/radio buttons".
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', 'article')
      ->setComponent($this->termFieldName, [
        'type' => 'options_buttons',
      ])
      ->save();
    $this->setUpCurrentUser(permissions: ['access content', 'create article content']);

    // Test terms are listed.
    $this->drupalGet('node/add/article');
    $this->assertSession()->pageTextContains('one');
    $this->assertSession()->pageTextContains('two');
    $this->assertSession()->pageTextContains('three');

    // Test terms translated are listed.
    $this->drupalGet('hu/node/add/article');
    $this->assertSession()->pageTextContains('translatedOne');
    $this->assertSession()->pageTextContains('translatedTwo');
    $this->assertSession()->pageTextContains('translatedThree');
  }

  /**
   * Setup translated terms in a hierarchy.
   */
  protected function setUpTerms(): void {
    $parent_vid = 0;
    foreach ($this->termTranslationMap as $name => $translation) {

      $term = $this->createTerm($this->vocabulary, [
        'name' => $name,
        'langcode' => $this->baseLangcode,
        'parent' => $parent_vid,
      ]);

      $term->addTranslation($this->translateToLangcode, [
        'name' => $translation,
      ]);
      $term->save();

      // Each term is nested under the last.
      $parent_vid = $term->id();

      $this->terms[] = $term;
    }
  }

  /**
   * Tests that weight field is translated and returns content as expected.
   */
  public function testTranslatedTermWeight(): void {
    $this->drupalLogin($this->rootUser);

    $i = 0;
    foreach ($this->terms as $term) {
      $term->setWeight($i)->save();
      $i++;
    }

    /**
     * Expected en tree:
     * term[0] | weight: 0
     * term[1] | weight: 1
     * term[2] | weight: 2
     */
    $this->drupalGet("/admin/structure/taxonomy/manage/{$this->vocabulary->id()}/overview");
    foreach ($this->terms as $term) {
      $this->assertSession()->fieldValueEquals("terms[tid:{$term->id()}:0][weight]", $term->getWeight());
    }

    /**
     * Expected hu tree:
     * term[0] | weight: 2
     * term[1] | weight: 1
     * term[2] | weight: 0
     */
    $i = 2;
    foreach ($this->terms as $term) {
      $term->getTranslation($this->translateToLangcode)->setWeight($i)->save();
      $i--;
    }
    $this->drupalGet("/{$this->translateToLangcode}/admin/structure/taxonomy/manage/{$this->vocabulary->id()}/overview");
    foreach ($this->terms as $term) {
      $this->assertSession()->fieldValueEquals("terms[tid:{$term->id()}:0][weight]", $term->getTranslation($this->translateToLangcode)->getWeight());
    }
  }

  /**
   * Get the final (leaf) term in the hierarchy.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   The final term in the hierarchy.
   */
  protected function getLeafTerm() {
    return $this->terms[count($this->termTranslationMap) - 1];
  }

}
