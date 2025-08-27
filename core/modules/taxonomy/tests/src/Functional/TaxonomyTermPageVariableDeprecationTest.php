<?php

declare(strict_types=1);

namespace Drupal\Tests\taxonomy\Functional;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
<<<<<<< HEAD
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
=======
>>>>>>> 8c9fd1e8574 (Issue #3535439 by acbramley, berdir, catch: Deprecate $variables['page'] in taxonomy term templates)

/**
 * Tests page variable deprecation.
 */
#[Group('taxonomy_term')]
<<<<<<< HEAD
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
=======
#[Group('legacy')]
>>>>>>> 8c9fd1e8574 (Issue #3535439 by acbramley, berdir, catch: Deprecate $variables['page'] in taxonomy term templates)
class TaxonomyTermPageVariableDeprecationTest extends TaxonomyTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'test_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createVocabulary(['vid' => 'test_page_variable']);
    EntityViewMode::create([
      'id' => 'taxonomy_term.test_page_variable',
      'targetEntityType' => 'taxonomy_term',
      'status' => TRUE,
      'enabled' => TRUE,
      'label' => 'Test page variable',
    ])->save();
  }

  /**
   * Tests that deprecations are thrown correctly for the page variable.
   */
  public function testPageVariableDeprecation(): void {
    $term = $this->createTerm(Vocabulary::load('test_page_variable'));
<<<<<<< HEAD
    $this->expectUserDeprecationMessage("'page' is deprecated in drupal:11.3.0 and is removed in drupal:13.0.0. Use 'view_mode' instead. See https://www.drupal.org/node/3542527");
=======
    $this->expectDeprecation("'page' is deprecated in drupal:11.3.0 and is removed in drupal:13.0.0. Use 'view_mode' instead. See https://www.drupal.org/node/3542527");
>>>>>>> 8c9fd1e8574 (Issue #3535439 by acbramley, berdir, catch: Deprecate $variables['page'] in taxonomy term templates)
    $this->drupalGet($term->toUrl());
    $this->assertSession()->pageTextContains('The page variable is set');

    $build = \Drupal::entityTypeManager()->getViewBuilder('taxonomy_term')->view($term, 'test_page_variable');
    $output = (string) \Drupal::service('renderer')->renderRoot($build);
    $this->assertStringNotContainsString('The page variable is set', $output);
  }

}
